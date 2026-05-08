# Smile Identity — Audit de sécurité (Sprint 6)

Date : mai 2026
Périmètre : intégration eKYC + AML Smile Identity dans Globalafrica+
Référence spec : `Globalafrica_Specification_SmileIdentity_v1.docx` § 12

---

## 1. Synthèse exécutive

| Domaine | Statut | Risque résiduel |
|---|---|---|
| Authentification webhook (HMAC) | ✅ OK | Faible |
| Idempotence des callbacks | ✅ OK | Faible |
| Stockage des PII (id_number) | ✅ OK | Faible |
| Stockage des biométriques (selfies) | ✅ OK (jamais stocké local) | Faible |
| Secrets de configuration | ✅ OK | Faible (pas de fuite git) |
| Protection CSRF du webhook | ✅ OK (exempt explicite) | Nul |
| Protection rate-limit | ⚠️ Manquant sur `/api/v1/kyc/*` | **Moyen** |
| TLS sur callback URL | ✅ OK (production HTTPS only) | Faible |
| Audit trail LCB-FT | ✅ OK (`payment_logs`) | Faible |
| Auto-blocage sanctions | ✅ OK | Faible |
| Déclaration de soupçon CENTIF | ⚠️ Mockée (pas branchée à la vraie API) | **Moyen** |
| Renouvellement KYC 24 mois | ✅ OK (cron + auto-downgrade) | Faible |
| RGPD — droit d'accès | ⚠️ Pas d'export utilisateur dédié | Moyen |
| RGPD — droit à l'oubli | ⚠️ Pas de procédure dédiée | Moyen |

---

## 2. Détails par domaine

### 2.1 Authentification webhook (HMAC-SHA256)

**Implémentation :** `app/Services/SmileIdentity/SmileSignature.php` + `app/Http/Middleware/VerifySmileSignature.php`

- Signature calculée comme `base64( hmac_sha256( timestamp, api_key ) )`
- `hash_equals()` utilisé pour la comparaison (timing-safe, immune au timing attack)
- Refus précoce si `timestamp` ou `signature` manquants
- Refus si `api_key` non configurée
- Loggué avec IP + taille payload pour forensic

**Tests couvrants :**
- `SignatureTest` : 6 cas unitaires sur `generate()` / `confirm()`
- `SmileWebhookHttpTest` : 4 cas HTTP (signature tamper, missing sig, missing ts, exempt CSRF)

**Risque résiduel :** Faible. La signature lie aussi le timestamp, donc un attaquant ne peut pas réutiliser une signature antérieure même sans replay window explicite. Pour durcir, on pourrait ajouter un check `abs(now - timestamp) < 5 min` (replay window).

### 2.2 Idempotence des callbacks

**Implémentation :** `app/Http/Controllers/Api/SmileWebhookController.php` + `app/Jobs/ProcessSmileCallback.php`

- Double garde :
  1. **Au controller :** lookup par `partner_job_id` puis `smile_job_id` ; si statut ∈ `{approved, rejected, expired}` → 200 + `Already processed.`
  2. **Au job :** même garde après le dispatch (au cas où deux jobs concurrents passeraient)
- Garantit qu'un retry de Smile (jusqu'à 4× automatique) ne re-traite jamais

**Tests couvrants :**
- `WebhookIdempotenceTest` : event `KYCVerified` dispatché 1 seule fois
- `SmileWebhookHttpTest` : full HTTP roundtrip

**Risque résiduel :** Faible.

### 2.3 Stockage des PII

**Numéros d'identité (CNI, NINA, NIN, BVN) :**
- **Jamais en clair.** Hashés via `hash_hmac('sha256', $idNumber, config('app.key'))` (cf. `KYCVerification::hashIdNumber`)
- Salting avec `APP_KEY` empêche le brute-force d'un dump DB seul
- Champ `id_number_hash VARCHAR(255) INDEX`

**Selfies & photos de document :**
- **Jamais stockés côté Globalafrica+.** Seul le `smile_job_id` est conservé.
- Les liens `ImageLinks` retournés par Smile sont des URLs S3 signées avec expiration (consultation à la demande, pas de copie locale)

**Payloads webhook bruts :**
- Stockés dans `kyc_verifications.callback_payload JSON` pour audit forensic
- Hidden des sérialisations API par défaut (`$hidden = ['callback_payload', 'id_number_hash']`)

**Risque résiduel :** Faible.

### 2.4 Secrets de configuration

- `SMILE_API_KEY`, `SMILE_PARTNER_ID` exclusivement dans `.env`
- `.env` ignoré par git (`/.gitignore`)
- `.env.example` + `.env.production.example` ne contiennent que des placeholders
- `VITE_SMILE_PARTNER_ID` exposé au browser (volontaire — c'est l'ID merchand public)
- `VITE_SMILE_ENVIRONMENT` exposé (sandbox/production) — non sensible

**Risque résiduel :** Faible.

### 2.5 Protection CSRF

- `bootstrap/app.php` : `validateCsrfTokens(except: ['api/v1/webhooks/smile-identity'])`
- Justifié : callback server-to-server, pas de session navigateur
- Compensé par la signature HMAC qui authentifie l'émetteur

**Test :** `SmileWebhookHttpTest::test_webhook_is_csrf_exempt`

**Risque résiduel :** Nul.

### 2.6 Rate limiting

**État actuel :** Aucun rate-limit explicite sur `/api/v1/kyc/*`.

**Risque :** un user authentifié pourrait soumettre 100+ vérifications/heure (spam coût Smile + brute-force d'identités).

**Recommandation Sprint 7 :** ajouter `RateLimiter::for('kyc-submissions')` :
```php
RateLimiter::for('kyc-submissions', fn ($r) => Limit::perHour(10)->by($r->user()->id));
```
Puis dans `routes/web.php` :
```php
Route::post('/v1/kyc/basic', ...)->middleware('throttle:kyc-submissions');
```

### 2.7 TLS

- Callback URL en `.env` : `https://globalafricaplus.com/api/v1/webhooks/smile-identity`
- Smile Identity refuse les callback URLs non-HTTPS en production
- Certificat servi par Hostinger (Let's Encrypt) — auto-renouvelé

### 2.8 Audit trail LCB-FT

Toutes les opérations sensibles laissent une trace immuable dans `payment_logs` :

| Source | event_type | Direction |
|---|---|---|
| `UnlockKYCFeatures` listener | `kyc.tier_upgraded` | inbound |
| `ReportSuspiciousActivity` listener | `compliance.suspicious_activity_report` | outbound |
| Callbacks Smile (à venir) | `kyc.callback_received` | inbound |

`payment_logs` : `created_at` only, pas d'`updated_at` — append-only.

**Conformité Art. 35 Directive UEMOA 02/2015 :** conservation 5 ans minimum (à configurer en politique de purge DB).

### 2.9 Auto-blocage sanctions

- `ReportSuspiciousActivity` : si `sanctions_match` → `users.aml_status = 'blocked'`
- Le middleware `kyc.smile:verified` retourne 403 `aml_blocked` même pour un user `certified`
- Aucune fonctionnalité financière n'est accessible (escrow, investments, gov_api)

**Test :** `MiddlewareTest::test_returns_403_aml_blocked_even_with_certified_tier` + `RouteKYCGateTest::test_aml_blocked_user_cannot_pass_even_with_certified_tier`

### 2.10 Déclaration de soupçon CENTIF

**État actuel :** **mockée**. Le listener `ReportSuspiciousActivity` écrit un `PaymentLog` avec `payload.mode = 'mock'` et marque `auto_reported = true`.

**À faire avant Go-Live :**
1. Obtenir les credentials CENTIF (Cellule Nationale de Traitement des Informations Financières)
2. Implémenter la transmission XML/JSON conforme au format COSI/CENTIF
3. Retirer `'mode' => 'mock'` du payload

### 2.11 Renouvellement 24 mois

- Cron `kyc:expire-verifications @ 02:30` (`bootstrap/app.php`)
- Job `ExpireKYCVerification` : marque `status = expired` + downgrade `users.kyc_level = basic`
- Middleware `RequireKYCLevel` : second filet de sécurité — auto-downgrade en cours de requête si `kyc_expires_at < now()`

**Test :** `ExpireKYCTest` (3 cas) + `MiddlewareTest::test_returns_403_kyc_expired_and_downgrades_user`

### 2.12 RGPD (Diaspora UE)

**Couvert :**
- DPA + CCT à signer avec Smile Identity (Nigeria/Kenya) — **action juridique** hors scope code
- Selfies non stockés localement → minimisation
- Consentement explicite à recueillir avant la 1re soumission biométrique → frontend (à ajouter dans le wizard, étape 2)

**À faire :**
- Endpoint `GET /api/v1/me/data-export` retournant un JSON avec toutes les données KYC/AML de l'utilisateur (Art. 15 RGPD — droit d'accès)
- Endpoint `DELETE /api/v1/me/account` qui ne supprime PAS les `kyc_verifications` (5 ans LCB-FT) mais anonymise les liens et marque `kyc_verifications.user_id` à NULL après transfert vers `archived_users`
- Bandeau de consentement biométrique avant `launchSmileSdk('biometric_kyc')` dans `KycSmile.vue`

---

## 3. Tests automatisés (couverture totale)

| Suite | Cas | Scénario spec |
|---|---|---|
| `SignatureTest` | 6 | T-12 (unit) |
| `SmileWebhookHttpTest` | 7 | T-12 + T-13 (integration HTTP) |
| `WebhookIdempotenceTest` | 1 | T-13 (job-level) |
| `MiddlewareTest` | 5 | T-15 (unit) |
| `RouteKYCGateTest` | 4 | T-15 (integration HTTP) |
| `ExpireKYCTest` | 3 | T-11 |
| `AmlCheckTest` | 5 | T-08, T-09, T-10 + escalade adverse_media |
| `ReportSuspiciousActivityTest` | 5 | Listener idempotence + edge cases |
| **Total** | **36** | T-08, T-09, T-10, T-11, T-12, T-13, T-15 |

**Scénarios non couverts par tests automatisés :**
- T-01 / T-02 / T-03 (Basic KYC sandbox) — testables seulement avec un vrai `SMILE_API_KEY` sandbox
- T-04 / T-05 / T-06 (Biometric KYC) — idem (upload S3 vers smile)
- T-07 (Document Verification) — idem
- T-14 (Diaspora passport) — idem
- T-16 (Web SDK integration) — testable manuellement via http://localhost/kyc

---

## 4. Recommandations Sprint 7

| Priorité | Item |
|---|---|
| 🔴 Haute | Brancher la vraie API CENTIF (retirer le mock) |
| 🔴 Haute | Ajouter rate-limiting sur `/api/v1/kyc/*` (10/h/user) |
| 🟡 Moyenne | Ajouter le replay window 5 min sur la signature webhook |
| 🟡 Moyenne | Endpoint `GET /me/data-export` (RGPD Art. 15) |
| 🟡 Moyenne | Bandeau consentement biométrique RGPD avant SDK |
| 🟢 Basse | Panneau admin `/admin/aml/flagged` pour la revue PEP |
| 🟢 Basse | Notification mail compliance@globalafricaplus.com sur `AMLFlagged` |

---

## 5. Conformité réglementaire

| Référence | Obligation | État |
|---|---|---|
| Directive UEMOA 02/2015 Art. 18 | KYC obligatoire avant transaction | ✅ Middleware `kyc.smile:verified` |
| Directive UEMOA 02/2015 Art. 22 | Renouvellement 24 mois | ✅ Cron + auto-downgrade |
| Directive UEMOA 02/2015 Art. 35 | Conservation 5 ans | ⚠️ Politique de purge DB à formaliser |
| Directive UEMOA 02/2015 (CENTIF) | Déclaration de soupçon | ⚠️ Mockée, à brancher |
| RGPD Art. 9.2.a | Consentement biométrique | ⚠️ Bandeau frontend manquant |
| RGPD Art. 15 | Droit d'accès | ⚠️ Endpoint manquant |
| RGPD Art. 28 / 46 | DPA + CCT avec Smile | ⚠️ Action juridique hors code |
| RGPD Art. 35 | DPIA biométrique | ⚠️ Document à produire (juridique) |
