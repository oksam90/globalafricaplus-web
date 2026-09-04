# Paiements — PawaPay (mobile money) + PayDunya (carte bancaire)

> Statut : les deux PSP sont **actifs en sandbox** et testés de bout en bout.
>
> | Moyen de paiement | PSP | Frais PSP | Réception porteur |
> |---|---|---|---|
> | **Mobile Money** (défaut) | PawaPay | par pays/opérateur | mobile money (payout PawaPay) |
> | **Carte bancaire** | PayDunya | **3,50 %** | virement bancaire (IBAN) |
>
> Le choix est proposé à l'utilisateur pour les **investissements**, les
> **abonnements** et les **achats de formations**.

---

## 1. Ce qui a été mis en place

| Élément | Fichier |
|---|---|
| Sélection du PSP | `config/payments.php` (`PAYMENT_GATEWAY`) |
| Credentials, marchés, tarifs | `config/pawapay.php` |
| Client HTTP Merchant API v2 | `app/Services/Payment/PawaPayClient.php` |
| Adaptateur PSP | `app/Services/Payment/Gateways/PawaPayGateway.php` |
| Moteur de frais & commission | `app/Services/Payment/FeeCalculator.php` |
| DTO de devis | `app/Services/Payment/DTOs/FeeQuote.php` |
| Callbacks | `POST /api/v1/webhooks/pawapay/{deposits\|payouts\|refunds\|checkouts}` |
| Traitement asynchrone | `app/Jobs/ProcessPawaPayCallback.php` |
| Endpoint devis | `POST /api/investments/quote` |
| Diagnostic | `php artisan pawapay:check [--quote=GA:3000]` |
| Tests | `tests/Feature/Payment/FeeCalculatorTest.php` |

Endpoints PawaPay utilisés (API v2) :

| Usage | Appel |
|---|---|
| Checkout hébergé | `POST /v2/paymentpage` → `redirectUrl` |
| Statut d'un paiement | `GET /v2/deposits/{depositId}` |
| Décaissement porteur | `POST /v2/payouts` |
| Remboursement | `POST /v2/refunds` |
| Marchés activés | `GET /v2/active-conf` |

Authentification : `Authorization: Bearer <PAWAPAY_API_TOKEN>`.
Base URL : `https://api.sandbox.pawapay.io` / `https://api.pawapay.io`.

---

## 2. Mise en service (Dashboard PawaPay)

1. **Developers → Callback URLs** — saisir les 4 URLs (obligatoire *avant* de
   pouvoir générer un token) :

   ```
   Checkouts : https://globalafricaplus.com/api/v1/webhooks/pawapay/checkouts
   Deposits  : https://globalafricaplus.com/api/v1/webhooks/pawapay/deposits
   Payouts   : https://globalafricaplus.com/api/v1/webhooks/pawapay/payouts
   Refunds   : https://globalafricaplus.com/api/v1/webhooks/pawapay/refunds
   ```

   `php artisan pawapay:check` les affiche déjà formatées.

2. **Developers → Create API Token** — générer le token, le coller dans
   `PAWAPAY_API_TOKEN`, puis `php artisan config:clear`.

3. **Developers → API Security** — « Sign all callbacks » fait signer les
   callbacks par PawaPay (RFC-9421 : `Signature`, `Signature-Input`,
   `Content-Digest`). L'activer est sans risque pour nos appels sortants.

   ⚠️ La **vérification** de ces signatures n'est pas encore implémentée côté
   application : `PAWAPAY_SIGNED_CALLBACKS` n'est aujourd'hui qu'un marqueur de
   configuration. La sécurité repose sur le fait que
   `ProcessPawaPayCallback` **re-vérifie systématiquement** chaque statut via
   `GET /v2/deposits/{id}` — un callback forgé ne peut donc rien valider.

   Ne pas activer « signed requests only » : notre client ne signe pas encore
   ses requêtes sortantes.

4. Vérifier : `php artisan pawapay:check` → liste les marchés réellement
   activés et signale les opérateurs sans barème connu.

---

## 3. Modèle de frais

L'investisseur supporte **100 %** des frais. Le porteur de projet encaisse
**exactement** le « Montant Reçu ».

```
Montant Reçu (net porteur)
  + commission GlobalAfrica+  (3 % / 2 % / 1 %, assise sur le net)
  + frais PawaPay décaissement (pays du projet)
  ───────────────────────────
  = montant devant arriver dans le wallet plateforme
  ÷ (1 − frais PawaPay collecte)     ← majoration (« gross-up »)
  ───────────────────────────
  = Montant Envoyé (débit investisseur)
```

Le gross-up (division) au lieu d'une simple addition garantit qu'après
prélèvement des frais de collecte par PawaPay, il reste de quoi payer le
porteur **et** le décaissement **et** la commission.

**Exemple — projet au Gabon, 3 000 FCFA reçus par le porteur :**

| Poste | Montant |
|---|---|
| Montant Reçu | 3 000 XAF |
| Commission GlobalAfrica+ (3 %) | + 90 XAF |
| Frais PawaPay collecte (Airtel Gabon, 2 %) | + 64 XAF |
| Frais PawaPay décaissement (1 %) | + 30 XAF |
| **Montant Envoyé** | **3 184 XAF** |

### Barème de commission (revenu plateforme)

| Montant de l'investissement ≥ | Taux appliqué |
|---|---|
| 5 € | 3,00 % |
| 5 000 € | 2,00 % |
| 20 000 € | 1,00 % |

Taux retenu pour le montant moyen : **3,00 %**.

Les **montants pivots ne sont jamais exposés** publiquement : l'API et l'UI
n'annoncent que « barème dégressif 3 % / 2 % / 1 % selon le montant »
(`payments.commission.display_thresholds = false`).

Pivots ajustables sans redéploiement via `COMMISSION_MIN_AMOUNT`,
`COMMISSION_PIVOT_2` et `COMMISSION_PIVOT_3` (exprimés en EUR).

### Assiette

Les frais sont calculés sur le **pays du projet** (spécification métier).
Basculer sur le pays de l'investisseur : `FEES_USE_PAYER_COUNTRY=true`.

### Prise en charge des frais de remboursement

| Cas | À la charge de |
|---|---|
| Investissement | **Investisseur** (majoration du Montant Envoyé) |
| Non-décaissement par le porteur | **Plateforme** |
| Litige après perception partielle/totale | **Porteur de projet** |

Codifié dans `config/payments.php` → `fees_borne_by`.

---

## 4. Tarifs PawaPay intégrés

`config/pawapay.php` → `markets` couvre les 20 marchés PawaPay avec, par
opérateur, les frais de collecte et de décaissement issus de
<https://www.pawapay.io/fees> (hors taxes).

- Les barèmes **par paliers** (Kenya, Tanzanie, Ouganda) sont approximés par
  leur **borne haute** : la plateforme ne sous-facture jamais.
- Les frais éventuellement prélevés **au payeur par l'opérateur** (ex. 1 % chez
  Airtel Gabon) sont affichés à titre informatif — ils sortent de notre flux.
- Un opérateur sans barème connu retombe sur `default_fees` (3 % / 2 %).

---

## 5. Tarification PayDunya (carte bancaire)

Source : <https://paydunya.com/service-fees> — grille « Standard » d'août 2026,
en % du montant de la transaction. La tranche dépend de **notre flux mensuel**
en FCFA, pas du montant de la transaction (`PAYDUNYA_VOLUME_TIER`, 1 par défaut).

| | 200 – 99 999 999 | 100 M – 500 M | + de 500 M |
|---|---|---|---|
| **Carte bancaire (CB)** | 3,50 % | 3,50 % | 3,50 % |
| PayIn Sénégal / Burkina / Côte d'Ivoire / Togo | 2,25 % | 2,20 % | 2,15 % |
| PayIn Bénin | 2,00 % | 1,85 % | 1,80 % |
| PayIn Cameroun | 2,00 % | 1,75 % | 1,50 % |
| PayOut Sénégal | 2,00 % | 1,95 % | 1,90 % |
| PayOut Burkina / CI / Togo / Mali | 2,00 % | 1,60 % | 1,50 % |
| PayOut Bénin | 1,50 % | 1,25 % | 1,80 % |
| PayOut Cameroun | 1,75 % | 1,60 % | 1,40 % |

La liste des canaux (`config/paydunya.php` → `channels`) suit la liste officielle
« Opérateurs Mobile Money Disponibles » de
<https://developers.paydunya.com/doc/FR/introduction>, `card` inclus.

**Sandbox → production PayDunya** : dans le dashboard, *Intégrez notre API* →
onglet APPLICATIONS → DÉTAILS → MODIFIER LA CONFIGURATION → « OUI,
L'APPLICATION EST PRÊTE », puis remplacer les clés de test et passer
`PAYDUNYA_MODE=live`.

---

## 6. Compte de réception du porteur — deux canaux

| Canal | Champs projet | Utilisé pour |
|---|---|---|
| **Mobile Money** (principal) | `payout_mobile_country`, `payout_mobile_provider`, `payout_mobile_number`, `payout_mobile_holder` | investissements réglés par mobile money |
| **Virement bancaire** (secondaire) | `payout_account_holder`, `payout_bank_name`, `payout_iban`, `payout_bic`, `payout_bank_country` | investissements réglés par carte bancaire |

`EscrowService::releaseMilestone()` privilégie le mobile money (décaissement
PawaPay automatique) et retombe sur l'IBAN sinon. Le badge « Compte vérifié »
est acquis dès qu'**un** des deux canaux est complet.

---

## 7. Points restant à trancher

1. **Sandbox → production** : `PAWAPAY_MODE=production` + nouveau token PawaPay ;
   `PAYDUNYA_MODE=live` + clés de production.
2. **Décaissement bancaire** : l'adaptateur SEPA / partenaire bancaire pour le
   canal IBAN reste à brancher (le canal mobile money, lui, est opérationnel).
3. **Callbacks PawaPay** : à renseigner dans le Dashboard (§2) pour que les
   paiements se confirment sans attendre le retour navigateur.
