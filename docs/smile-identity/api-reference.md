# Smile Identity — API Reference

Version 1.0 — Mai 2026
Base URL : `https://globalafricaplus.com`
Auth : Bearer / cookie session Sanctum (sauf indication contraire)

---

## Sommaire

1. [Endpoints utilisateur](#endpoints-utilisateur)
2. [Endpoint webhook](#endpoint-webhook)
3. [Codes de résultat Smile](#codes-de-résultat-smile)
4. [Niveaux KYC et capacités](#niveaux-kyc-et-capacités)
5. [Erreurs middleware `kyc.smile`](#erreurs-middleware-kycsmile)
6. [Notifications utilisateur](#notifications-utilisateur)
7. [Codes Test sandbox](#codes-test-sandbox)

---

## Endpoints utilisateur

Tous sous `auth` (Sanctum). Préfixe `/api/v1/kyc`.

### `GET /api/v1/kyc/status`

Récupère l'état KYC + AML courant de l'utilisateur connecté.

**Réponse 200 :**
```json
{
  "kyc_level": "verified",
  "kyc_verified_at": "2026-04-15T10:23:00Z",
  "kyc_expires_at": "2028-04-15T10:23:00Z",
  "is_expired": false,
  "aml_status": "clear",
  "aml_last_checked_at": "2026-04-15T10:25:00Z",
  "selfie_registered": true,
  "latest": {
    "id": 42,
    "job_type": "biometric_kyc",
    "status": "approved",
    "result_code": "0810",
    "result_text": "Approved",
    "confidence_value": "95.50",
    "submitted_at": "2026-04-15T10:22:30Z",
    "completed_at": "2026-04-15T10:23:00Z",
    "expires_at": "2028-04-15T10:23:00Z"
  }
}
```

---

### `GET /api/v1/kyc/history`

Liste paginée des vérifications KYC de l'utilisateur (15 / page).

**Réponse 200 :** Laravel paginator standard.

---

### `POST /api/v1/kyc/basic`

Soumet une **Basic KYC** (Job Type 5) — vérification d'identité auprès de l'autorité gouvernementale.

**Payload :**
```json
{
  "country": "SN",
  "id_type": "NATIONAL_ID",
  "id_number": "1234567890123",
  "first_name": "Aminata",
  "last_name": "Diop",
  "dob": "1990-05-15"
}
```

**Réponse 202 (accepted) :**
```json
{
  "message": "Vérification soumise. Résultat attendu via callback.",
  "verification": { "id": 42, "status": "processing", "partner_job_id": "uuid…" },
  "smile": { "job_id": "uuid…", "smile_job_id": "0000020855", "status": "submitted" }
}
```

**Réponse 502 :** échec de soumission (transport ou erreur Smile).
**Réponse 422 :** validation des champs.

---

### `POST /api/v1/kyc/biometric`

Soumet une **Biometric KYC** (Job Type 1) — selfie + comparaison faciale avec la photo de l'autorité.

**Payload :**
```json
{
  "country": "SN",
  "id_type": "NATIONAL_ID",
  "id_number": "1234567890123",
  "selfie": "data:image/jpeg;base64,/9j/4AAQ..."
}
```

> Le préfixe `data:image/...;base64,` est automatiquement strippé côté serveur.

**Réponse 202 :** identique à `/basic`. Le résultat final arrive via webhook (`Selfie_Check`, `Liveness_Check`, `Selfie_To_ID_Authority_Compare`, `Register_Selfie`).

---

### `POST /api/v1/kyc/document`

Soumet une **Document Verification** (Job Type 6) — OCR du document + comparaison selfie ↔ photo du document.

**Payload :**
```json
{
  "country": "SN",
  "id_type": "PASSPORT",
  "id_number": "P12345678",
  "selfie": "data:image/jpeg;base64,...",
  "id_document_front": "data:image/jpeg;base64,..."
}
```

**Réponse 202 :** idem.

---

### `POST /api/v1/kyc/aml`

Lance un **AML Check** (Job Type 10) — synchrone, résultat immédiat.

**Payload :**
```json
{
  "full_name": "Aminata Diop",
  "countries": ["SN", "FR"],
  "birth_year": "1990"
}
```

**Réponse 201 :**
```json
{
  "message": "Screening AML effectué.",
  "screening": { "id": 7, "risk_level": "low", "sanctions_match": false, "pep_match": false, "adverse_media_match": false },
  "risk_level": "low",
  "flags": { "sanctions": false, "pep": false, "adverse_media": false }
}
```

**Effets de bord :**
- `users.aml_status` mis à jour à `flagged` si `sanctions_match` ou `risk_level=critical`
- Event `AMLFlagged` dispatché si **n'importe quel** match → notif admins + listener `ReportSuspiciousActivity`
- Si `sanctions_match` → user auto-bloqué (`aml_status = blocked`) + log CENTIF dans `payment_logs`

---

### `POST /api/v1/kyc/web-token`

Génère un token à usage unique pour le **Hosted Web SDK** Smile Identity.

**Payload (optionnel) :**
```json
{ "product": "biometric_kyc" }
```
Valeurs : `biometric_kyc` (défaut) | `doc_verification` | `authentication` | `basic_kyc` | `enhanced_kyc`.

**Réponse 201 :**
```json
{
  "token": "eyJhbGc…",
  "job_id": "uuid…",
  "raw": { "...payload Smile brut..." }
}
```

---

## Endpoint webhook

### `POST /api/v1/webhooks/smile-identity`

Réception des callbacks Smile Identity. **Authentifié par signature HMAC**, exempt CSRF.

**Headers requis :** aucun spécifique (signature dans le body JSON).

**Payload entrant (extrait) :**
```json
{
  "timestamp": "2026-05-04T20:10:33.123Z",
  "signature": "base64(hmac_sha256(timestamp, api_key))",
  "SmileJobID": "0000020855",
  "ResultCode": "0810",
  "ResultText": "Approved",
  "ConfidenceValue": "95.5",
  "PartnerParams": {
    "user_id": "42",
    "job_id": "uuid-côté-globalafrica",
    "job_type": 1
  },
  "Actions": {
    "Selfie_Check": "Passed",
    "Liveness_Check": "Passed",
    "Verify_ID_Number": "Verified",
    "Selfie_To_ID_Authority_Compare": "Completed",
    "Register_Selfie": "Approved"
  }
}
```

**Réponses :**

| Code | Body | Cas |
|---|---|---|
| 200 | `{"message":"OK"}` | Callback accepté, dispatché vers `ProcessSmileCallback` |
| 200 | `{"message":"Already processed."}` | Idempotence — verification déjà finalisée |
| 401 | `{"message":"Unauthorized"}` | Signature absente ou invalide |
| 422 | `{"message":"Missing job identifiers."}` | Ni `SmileJobID` ni `PartnerParams.job_id` |

---

## Codes de résultat Smile

Source : spec § 7.2 + dictionnaire `resources/js/utils/smileResultCodes.js`.

| Code | Signification | Statut interne | Tier accordé |
|---|---|---|---|
| 0810 | Approved | `approved` | `verified` (basic) / `certified` (biometric ≥ 90% conf. ou doc verif) |
| 0811 | Provisionally Approved | `approved` | `verified` |
| 0812 | Rejected | `rejected` | — |
| 0814 | Under Review | `processing` | — |
| 0913 | Invalid ID | `rejected` | — |
| 0914 | ID Not Found | `rejected` | — |
| 0915 | ID Authority Unavailable | `processing` | — (Smile retry auto) |
| 1020 | AML — Match | (sync) | — |
| 1022 | AML — No match | (sync) | — |

---

## Niveaux KYC et capacités

Source : `config/smile.php`.

| Tier | `max_daily_eur` | Features débloquées |
|---|---|---|
| `basic` | 0 | `browse`, `apply`, `message` |
| `verified` | 5 000 | `invest`, `subscribe`, `mentor` |
| `certified` | 50 000 | `escrow`, `gov_api`, `high_value` |

Validité : 24 mois (`smile.kyc_expiry_months`). Au-delà : downgrade auto vers `basic`.

---

## Erreurs middleware `kyc.smile`

Préfixe d'utilisation : `kyc.smile:verified` ou `kyc.smile:certified`.

| Code HTTP | `error` | Sens |
|---|---|---|
| 401 | `auth_required` | Pas authentifié |
| 403 | `kyc_insufficient` | Tier inférieur au requis. Body : `required_level`, `current_level` |
| 403 | `kyc_expired` | `kyc_expires_at` dans le passé. User auto-downgradé à `basic`. |
| 403 | `aml_blocked` | `users.aml_status = blocked` (auto-set sur sanctions match) |
| 200 | — | Tier suffisant + non expiré + non bloqué |

---

## Notifications utilisateur

Channels : `mail` + `database` (cloche dans `SiteHeader`).

| Type | Destinataire | Trigger |
|---|---|---|
| `kyc_verified` | User | `KYCVerified` event (callback approve) |
| `kyc_rejected` | User | `KYCRejected` event (callback rejette) |
| `aml_flagged` | Admins | `AMLFlagged` event (any match) |

Endpoints `/api/notifications` :
- `GET /api/notifications` — liste paginée
- `GET /api/notifications/unread-count`
- `POST /api/notifications/{id}/read`
- `POST /api/notifications/read-all`
- `DELETE /api/notifications/{id}`

---

## Codes Test sandbox

Source : spec § 10.1.

Le **dernier chiffre** de `id_number` détermine le résultat simulé (sandbox uniquement) :

| Dernier chiffre | Résultat simulé | Code | Cas couvert |
|---|---|---|---|
| 0 | Approved | 0810 | T-01, T-04 (kyc → verified/certified) |
| 1 | Rejected | 0812 | T-02, T-05 (kyc reste basic) |
| 2 | Provisionally Approved | 0811 | T-06 (kyc → verified temporaire) |
| 3 | Error / ID Not Found | 0914 | T-03 (suggestion document verification) |
| 4 | Custom (via `partner_params`) | variable | Personnalisation |

**Préfixe pour IDs uniques :** ajouter 2 caractères avant le dernier chiffre.

Exemples :
- `00000000000` → Approved
- `0000000AB0` → Approved (ID unique pour test)
- `00000000001` → Rejected
- `0000000XY1` → Rejected (ID unique)
- `00000000004` + `partner_params.sandbox_result = "0"` → Approved avec données custom

> ⚠️ Le paramètre `sandbox_result` doit être supprimé avant le passage en production.

---

## Variables d'environnement

```env
# Backend (jamais exposé au browser)
SMILE_PARTNER_ID=8599
SMILE_API_KEY=4e3a2b56-3557-436b-a389-ace963dbd672
SMILE_ENVIRONMENT=sandbox      # ou 'production'
SMILE_CALLBACK_URL=https://globalafricaplus.com/api/v1/webhooks/smile-identity

# Frontend (exposé via Vite — non sensibles)
VITE_SMILE_PARTNER_ID="${SMILE_PARTNER_ID}"
VITE_SMILE_ENVIRONMENT="${SMILE_ENVIRONMENT}"

# Optionnel — tuning HTTP
SMILE_HTTP_TIMEOUT=15
SMILE_HTTP_RETRY=2
SMILE_HTTP_RETRY_SLEEP=500
```

---

## Migration progressive `kyc` → `kyc.smile`

L'ancien middleware `kyc` (CheckKyc, IDnorm-era) est conservé pour ne pas casser le legacy. Les nouvelles routes utilisent `kyc.smile:verified` qui ajoute :

- Auto-downgrade sur expiration 24 mois
- Bloque les utilisateurs `aml_status = blocked`
- Réponses JSON avec `error` codes exploitables côté SPA

Routes déjà migrées (Sprint 4) :
- `gouvernement/*` (CRUD appels, zones)
- entrepreneur (POST/PATCH/DELETE projects, publish, updates)

Routes restant sur `kyc` legacy : aucune (les 2 groupes ont été migrés).

---

## Liens utiles

- Documentation Smile Identity : https://docs.usesmileid.com
- Test data : https://docs.usesmileid.com/supported-id-types/for-individuals-kyc/backed-by-id-authority/test-data
- AML Check : https://docs.usesmileid.com/products/for-individuals-kyc/aml-check
- Audit sécurité : [./security-audit.md](./security-audit.md)
- Spec interne : `Globalafrica_Specification_SmileIdentity_v1.docx`
