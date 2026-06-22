# Dossier d'Architecture Technique et Fonctionnelle de l'Existant
## Plateforme **Globalafrica+** (Africa+)

| Élément | Valeur |
|---|---|
| Version du dossier | 1.0 |
| Date d'établissement | 19 juin 2026 |
| Périmètre | Codebase `C:\xampp\htdocs\globalafrica+` (branche `main`, HEAD `449ad07`) |
| Auteur | Analyse statique du code source |
| Statut | Document de référence — vue exhaustive de l'existant |

---

## 1. Synthèse exécutive

**Globalafrica+** est une plateforme panafricaine SaaS multi-rôles dédiée à l'écosystème entrepreneurial africain et à la diaspora. Elle agrège, dans une seule application monolithique Laravel + Vue.js, **dix domaines fonctionnels** :

1. **Projets** : levée de fonds (crowdfunding equity / don / prêt / récompense) ;
2. **Investissement** & **Escrow** : paiement, séquestre et libération par jalons ;
3. **Mentorat** : annuaire de mentors, disponibilités, sessions, reviews ;
4. **Emploi** : offres liées aux projets, candidatures et matching compétences ;
5. **Formalisation** : parcours pays-par-pays + business plans + microfinance ;
6. **Diaspora** : guides pays + simulateur de transfert/investissement ;
7. **Gouvernement** : appels à projets et zones économiques spéciales ;
8. **Formations** : catalogue payant de modules en ligne ;
9. **Abonnements** : 4 plans (Free / Starter / Pro / Enterprise) ;
10. **KYC / AML** : conformité réglementaire (UEMOA, RGPD, LCB-FT).

L'architecture est un **monolithe modulaire** propulsé par **Laravel 12 / PHP 8.2** côté API et **Vue 3 (Composition API) / Pinia / Vue Router** côté SPA, servis ensemble par le même serveur (Nginx + PHP-FPM 8.2). La persistence est **MySQL 8**, les jobs et le cache utilisent les drivers `database`. Le paiement passe par **PayDunya** (UEMOA/CEMAC, mobile-money + carte) ; l'identité numérique par **Smile Identity** (eKYC + AML). L'authentification combine session web Laravel et **Google OAuth** via Socialite.

L'application est **multilingue (FR par défaut)**, **multi-devises (EUR / XOF / XAF / USD)** et **multi-rôles** (un utilisateur peut endosser plusieurs rôles avec un rôle actif courant et un profil enrichi par rôle).

---

## 2. Contexte fonctionnel & Rôles

### 2.1 Acteurs (rôles RBAC)

Définis dans `Role::slug` (table `roles`, seedés par `DatabaseSeeder`) :

| Slug | Libellé | Description |
|---|---|---|
| `entrepreneur` | Entrepreneur | Porteur de projet : publie/édite des projets, recrute, demande mentorat. |
| `investor` | Investisseur | Diaspora ou institutionnel ; investit, valide jalons escrow. |
| `government` | Gouvernement | Ministère / agence : publie des appels à projets et gère des ZES. |
| `jobseeker` | Chercheur d'emploi | Candidatures, gestion de compétences. |
| `mentor` | Mentor | Expert : disponibilités, sessions, reviews. |
| `admin` | Administrateur | Équipe Globalafrica+ : modération, analytics, CRUD plateforme. |

Particularités :
- **Multi-rôles natif** : table pivot `role_user` + `active_role_slug` sur `users` + `role_profiles` (profil JSON par rôle avec champ `completion`).
- **Dashboard contextualisé** : `DashboardController::index` route vers `entrepreneurData / investorData / mentorData / jobseekerData / governmentData / adminData` selon `active_role_slug`.
- Bascule rôle UI : composant `RoleSwitcher.vue` + endpoint `POST /api/me/active-role`.

### 2.2 Plans d'abonnement

Définis dans `subscription_plans` (seeder `SubscriptionSeeder`) :

| Plan | €/mois | €/an | Limites principales |
|---|---|---|---|
| **Free** | 0 | 0 | Lecture seule (browse, consultation guides, profil de base, 50 Mo stockage). |
| **Starter** | 9,99 | 99,99 | 3 projets, 10 candidatures/mois, 2 demandes mentorat/mois, 500 Mo. |
| **Pro** | 29,99 | 299,99 | Projets/candidatures/mentorat illimités, matching IA, badge Pro, 5 Go. **Requis pour investir.** |
| **Enterprise** | 79,99 | 799,99 | Multi-utilisateurs, appels gouvernementaux, ZES, API dédiée, SLA 99,9%, 50 Go. Formulaire « Nous contacter » dédié. |

Cycle de facturation `monthly|yearly` ; statut `active|trialing|past_due|cancelled|expired` ; **garantie satisfait 30 jours** (`trial_ends_at` + `RefundService`).

### 2.3 Niveaux KYC (Smile Identity)

Configurés dans `config/smile.php → kyc_levels` :

| Niveau | Plafond / jour | Features débloquées |
|---|---|---|
| `basic` | 0 € | browse, apply, message |
| `verified` | 5 000 € | invest, subscribe, mentor |
| `certified` | 50 000 € | escrow, gov_api, high_value |

Validité **24 mois** (`kyc_expiry_months`), auto-downgrade automatique au passage (`RequireKYCLevel`) + job planifié `ExpireKYCVerification` (cron 02:30 quotidien).

---

## 3. Architecture technique générale

### 3.1 Stack

| Couche | Technologie | Version | Rôle |
|---|---|---|---|
| Runtime PHP | PHP-FPM | 8.2 | Exécution de l'API et du rendu Blade. |
| Framework backend | Laravel | 12 | Routing, ORM Eloquent, queues, scheduling, validation. |
| Auth fédérée | Laravel Socialite | 5.27 | Google OAuth (OpenID Connect). |
| Gateway paiement | paydunya/paydunya | 1.0 | Création checkout / DirectPay / IPN. |
| Frontend | Vue 3 | 3.5 | SPA Composition API. |
| Router | vue-router | 4.6 | History API client-side. |
| Store | Pinia | 3.0 | `auth`, `theme`, `ui`. |
| Styling | Tailwind CSS | 4.0 (plugin Vite) | Dark mode classe `.dark`. |
| Build | Vite | 7 + `laravel-vite-plugin` 2 | Bundling SPA et HMR. |
| DB | MySQL (PDO) | 8 | Persistence (driver via `DB_CONNECTION=mysql`). |
| Cache / Sessions / Queue | MySQL | — | Driver `database` pour les trois. |
| Reverse proxy | Nginx | — | TLS 1.2/1.3, HSTS, headers sécurité, gzip, cache assets 1 an. |
| Webhooks/KYC | Smile Identity REST API | 2.0.0 | Job types 1, 5, 6, 10 + AML. |
| CI/CD | GitHub Actions + script `deploy/deploy.sh` | — | Déploiement atomique (symlinks `releases/` + `current`). |
| Test | PHPUnit | 11 | Suite tests Smile (auth, signature, idempotence, rate limits…). |

### 3.2 Vue d'ensemble (mode déploiement)

```
                    +-------------------+
                    |  Utilisateur SPA  |
                    +---------+---------+
                              | HTTPS (Let's Encrypt)
                              v
                    +-------------------+
                    |   Nginx (443)     |  HSTS, X-Frame, CSP-lite, gzip
                    +---------+---------+
                              | FastCGI
                              v
                    +-------------------+
                    | PHP-FPM (Laravel) |
                    +----+--+--+--+-----+
                         |  |  |  |
   PayDunya  <----IPN----+  |  |  +----> Smile Identity (REST + IPN)
                            |  |
                            |  +-----> Google OAuth (Socialite)
                            v
                    +-------------------+
                    |     MySQL 8       |  jobs, cache, sessions, métier
                    +-------------------+

   Worker queue (`php artisan queue:work` via Supervisor):
   - ProcessPayDunyaWebhook       (IPN PayDunya → réconciliation)
   - ProcessSmileCallback         (IPN Smile → MAJ KYC + events)
   - ProcessEscrowRelease         (DirectPay sortie)
   - ProcessInstallmentDue        (cron 04:00 — facture la prochaine échéance)
   - ProcessAutoRefund            (cron 03:15 — escrow > 90 j)
   - ExpireKYCVerification        (cron 02:30 — expirations KYC)
```

### 3.3 Topologie de fichiers du dépôt

```
globalafrica+/
├── app/
│   ├── Console/Commands/        (3 commandes : SmileSandboxProbe, MigrateLegacyKycSessions, NotifyLegacyImportedKyc)
│   ├── Events/                  (3 : AMLFlagged, KYCRejected, KYCVerified)
│   ├── Http/
│   │   ├── Controllers/Api/     (27 controllers — voir §4)
│   │   ├── Middleware/          (7 middlewares — voir §3.5)
│   │   └── Requests/            (StoreProjectRequest, UpdateProjectRequest, SubmitKYCRequest)
│   ├── Jobs/                    (6 jobs queue)
│   ├── Listeners/               (3 : ReportSuspiciousActivity, SendKYCNotification, UnlockKYCFeatures)
│   ├── Mail/                    (ContactInquiry — formulaire Enterprise)
│   ├── Models/                  (35 modèles Eloquent)
│   ├── Notifications/           (3 notifications DB channel)
│   ├── Policies/                (ProjectPolicy)
│   ├── Providers/               (AppServiceProvider + PayDunyaServiceProvider)
│   ├── Services/
│   │   ├── Payment/             (8 services : Gateway PayDunya, Escrow, Investment, Subscription,
│   │   │                         Installment, Training, Refund, CurrencyService + Factory + Interface + DTOs)
│   │   └── SmileIdentity/       (SmileIdentityService 494 lignes + SmileSignature + Jobs + DTOs)
│   └── Support/PiiRedactor.php  (helper anonymisation logs)
├── bootstrap/app.php            (config Laravel 12 — middlewares, schedule, alias)
├── config/                      (12 configs : app, auth, paydunya, smile, services, contact, …)
├── database/
│   ├── migrations/              (32 migrations daté ~04/2026 → 05/2026)
│   ├── seeders/                 (11 seeders thématiques + DatabaseSeeder)
│   └── factories/
├── deploy/
│   ├── deploy.sh                (script bash atomic deploy)
│   ├── provision.sh             (provisioning VPS)
│   ├── nginx.conf               (vhost + TLS + headers + cache assets)
│   └── supervisor-worker.conf   (worker queue daemon)
├── docs/
│   ├── API_RESPONSES.md
│   ├── audit/generate-audit-docx.cjs
│   └── smile-identity/          (api-reference.md, security-audit.md)
├── public/
│   ├── index.php
│   ├── build/                   (assets Vite)
│   └── brand/, favicon.ico, robots.txt
├── resources/
│   ├── js/                      (SPA Vue — voir §5)
│   ├── css/                     (Tailwind entry)
│   └── views/                   (app.blade.php — shell SPA + emails)
├── routes/
│   ├── web.php                  (toutes les routes incl. /api/*)
│   └── console.php
├── storage/
└── tests/                       (1 unit + 14 features dont 12 Smile)
```

---

## 4. Architecture API & Contrôleurs

### 4.1 Convention de routage

Toutes les routes API sont **préfixées `/api`** mais déclarées dans **`routes/web.php`** (et non `routes/api.php`) afin de bénéficier du **middleware `web`** (sessions + CSRF + cookies). L'authentification est **session-based** (cookie `africaplus_session`, table `sessions`). Pas de JWT, pas de Sanctum API tokens.

Le SPA est servi par une route catch-all à la fin de `web.php` :
```php
Route::get('/{any?}', fn () => view('app'))->where('any', '^(?!api|auth/google).*$');
```
Les routes OAuth (`/auth/google/redirect`, `/auth/google/callback`) sont **hors `/api`** pour que Socialite puisse écrire dans la session.

### 4.2 Cartographie des contrôleurs

| Contrôleur | Lignes | Endpoints principaux | Rôle métier |
|---|---:|---|---|
| **AuthController** | 204 | `POST /auth/register`, `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`, `GET /auth/google/redirect`, `GET /auth/google/callback` | Inscription multi-rôles, login session, Google OAuth (3 stratégies : par google_id, par email, ou création + rôle `investor` par défaut). |
| **MeController** | 109 | `GET/PATCH /me`, `POST /me/roles`, `DELETE /me/roles/{slug}`, `POST /me/active-role` | Gestion du profil utilisateur, multi-rôles. |
| **RoleProfileController** | 151 | `GET/PUT /me/profiles/{roleSlug}` | Profil enrichi par rôle (JSON + completion %). |
| **ProjectController** | 240 | CRUD `/projects`, `/projects/{slug}`, follow/unfollow, mes-projets, updates | Crowdfunding. CRUD soumis à `role:entrepreneur,admin` + `subscribed` + `kyc.smile:verified`. |
| **CategoryController / SectorController / SdgController** | 17/219/15 | Référentiels publics + CRUD admin secteurs (point 4 optim) | Catégories, sous-catégories, ODD ONU. |
| **DiasporaController** | 414 | Stats, countries (CRUD), simulateur, projects | Guides pays (12 pays), simulateur investissement diaspora, CRUD admin pour les guides. |
| **FormalizationController** | 226 | Stats, steps pays, templates, partners, mon-parcours | Parcours formalisation entreprise (multi-pays), business plans, microfinance. |
| **MentoratController** | 437 | Annuaire, profil mentor, request/respond/complete, sessions, reviews, availabilities | Marketplace mentor / mentee complet. |
| **JobController** | 240 | Listings, skills, candidatures, applications par projet | Emploi : candidatures projet, gestion compétences (sync). |
| **GovernmentController** | 734 | Appels (CRUD complet user + admin), zones (CRUD), candidatures, review | Portail gouvernemental. |
| **InvestmentController** | 161 | `POST /investments`, `GET /investments/mine`, `POST /investments/verify` | Investissement + retour PayDunya. |
| **EscrowController** | 120 | Submit/approve/reject jalons, milestones mine + par projet | Séquestre par jalons. |
| **InstallmentController** | 105 | Mine, createForInvestment, payNext | Paiement fractionné. |
| **SubscriptionController** | 170 | plans, my, subscribe, cancel, refund, verify | Cycle abonnement. |
| **TrainingController** | 154 | Catalogue, show, mine, purchase, verify, refund | Formations payantes. |
| **ExchangeRateController** | 43 | `/exchange-rates/xof-eur`, `/exchange-rates/{from}/{to}` | Taux de change live. |
| **NotificationController** | 80 | index, unread-count, markRead, markAllRead, destroy | Cloche notif DB channel. |
| **AdvertisingController** | 310 | Banners (impression + click track), partners, testimonials, CRUD admin | Bannières + partenaires + témoignages. |
| **DashboardController** | 349 | `GET /dashboard` | KPIs role-contextuels (6 stratégies match selon active_role_slug). |
| **StatsController** | 23 | `GET /stats` | Compteurs publics (homepage). |
| **AdminController** | **845** | `/admin/{analytics,users,trainings,moderation,config,uploads}` + CRUD étendu | Console admin : 10 optimisations métier (abonnements, KYC override, etc.). |
| **SmileKYCController** | 351 | `/v1/kyc/{status,history,basic,biometric,document,web-token,consent,aml}` | Endpoints eKYC + journal RGPD consentement biométrique. |
| **SmileWebhookController** | 55 | `POST /v1/webhooks/smile-identity` | Réception IPN Smile (signature HMAC + idempotence). |
| **WebhookController** | 25 | `POST /v1/webhooks/paydunya` et alias `/webhooks/paydunya` | IPN PayDunya. |
| **ContactController** | 68 | `POST /contact` | Formulaire Entreprise (rate-limit `contact-form` 5/h/IP). |

**Total : 5 865 lignes de contrôleurs API.**

### 4.3 Endpoints publics vs. authentifiés

Les routes publiques (non authentifiées) sont systématiquement des **lectures** ou des soumissions externes signées :
- Référentiels : `/stats`, `/categories`, `/sdgs`, `/sectors[*]`, `/exchange-rates/*` ;
- Portails portail-pays : `/diaspora/*`, `/formalisation/*`, `/emploi/*`, `/mentorat/*` (lectures) ;
- Catalogues : `/trainings`, `/subscription/plans`, `/projects[*]` ;
- Publicité / témoignages : `/advertising/{banners,partners,testimonials}` ;
- Auth : `/auth/{register,login,me}` ;
- Webhooks : `/v1/webhooks/{paydunya,smile-identity}` (protégés par signature + throttle IP) ;
- Contact : `/contact` (throttle 5/h).

Le reste est sous `Route::middleware('auth')` puis stratifié par rôle / abonnement / KYC / AML — voir §3.5.

### 4.4 Webhooks externes

#### PayDunya IPN
- Routes : `POST /api/v1/webhooks/paydunya` + alias `/api/webhooks/paydunya` (legacy).
- Middleware : `throttle:webhooks` (200/min/IP) + `paydunya.webhook` (vérification HMAC-SHA512).
- CSRF exempté explicitement dans `bootstrap/app.php`.
- Dispatch : `ProcessPayDunyaWebhook` (queue `default`).

#### Smile Identity IPN
- Route : `POST /api/v1/webhooks/smile-identity`.
- Middleware : `throttle:webhooks` + `smile.webhook` (HMAC-SHA256 base64, comparaison timing-safe via `hash_equals`, replay window 300 s, clock skew 60 s — `config/smile.webhook`).
- Idempotence double : controller (lookup `partner_job_id` puis `smile_job_id`) + job `ProcessSmileCallback`.
- Émission d'événements : `KYCVerified`, `KYCRejected`, `AMLFlagged` → listeners `UnlockKYCFeatures`, `ReportSuspiciousActivity`, `SendKYCNotification`.

---

## 5. Architecture frontend — SPA Vue 3

### 5.1 Bootstrap

- Entrée : `resources/js/app.js` → mount sur `#app` du template `app.blade.php`.
- Composant racine : `App.vue` (header / loader / router-view / footer / toasts).
- Pinia stores :
  - `auth.js` : utilisateur, rôles, abonnement, KYC, AML, helpers `investorProfileReady` (= Pro/Ent + KYC verified + AML clear), `canInvest`, `globalCompletion`, `fetchUser()`, `login/register/logout`, `switchRole/addRole/removeRole`, `updateRoleProfile`.
  - `theme.js` : dark mode persistant.
  - `ui.js` : `startNav/endNav` (NavigationLoader), toasts.

### 5.2 Routeur

Défini dans `resources/js/router/index.js` (40+ routes). Garde globale `beforeEach` :
- Démarre l'indicateur de navigation ;
- Si non-bootstrappé : `await auth.fetchUser()` (bloquant pour routes `meta.requiresAuth`, non-bloquant sinon — optimisation TTI) ;
- Redirige vers `/connexion?redirect=...` si `requiresAuth` sans user.

Chargement **paresseux par page** (`() => import(...)`) — code-split natif Vite.

### 5.3 Cartographie des pages

| Domaine | Pages clés |
|---|---|
| Public / Marketing | `Home.vue` (531 l.), `Tarifs.vue` (462 l., formulaire Enterprise), `Diaspora.vue`, `Formalisation.vue`, `Emploi.vue`, `Mentorat.vue`, `Gouvernement.vue` |
| Projets | `projects/Index`, `projects/Show`, `projects/Mine`, `projects/CreateEdit` |
| Secteurs | `sectors/Index`, `sectors/Show` |
| Formations | `formations/Index`, `formations/Show`, `formations/MesFormations` |
| Paiement | `paiement/Succes`, `paiement/Annule` (retours PayDunya) |
| Escrow | `escrow/MesJalons` |
| Profil | `profile/Index`, `profile/Edit`, `profile/AddRole` |
| Mentorat | `mentorat/MentorProfile`, `mentorat/MesMentorats` |
| Emploi | `emploi/MesCandidatures`, `emploi/MesCompetences` |
| Formalisation | `formalisation/MonParcours`, `formalisation/BusinessPlans` |
| Gouvernement | `gouvernement/CallShow`, `gouvernement/MesAppels`, `gouvernement/Applications`, `gouvernement/MesZones` |
| Diaspora | `diaspora/CountryGuide` |
| Auth | `auth/Login`, `auth/Register` |
| Dashboard | `Dashboard.vue` (800 l., 6 vues conditionnelles selon rôle) |
| KYC | `KycSmile.vue` (780 l., Smile Web SDK iframe) |
| Admin | `admin/{Users,Moderation,Analytics,Mentors,Trainings,Sectors,CountryGuides,Calls,Zones,Partners,Testimonials}.vue` |
| Légal | `legal/Cgu`, `legal/Confidentialite`, `legal/Dpa` |
| 404 | `NotFound.vue` |

### 5.4 Composants partagés

- `SiteHeader.vue` (175 l.), `SiteFooter.vue` (150 l.)
- `Modal.vue` (187 l.), `ToastContainer.vue`, `NavigationLoader.vue`
- `NotificationBell.vue` (214 l.) — cloche notif avec polling `/notifications/unread-count`
- `EscrowMilestones.vue` (335 l.), `InvestmentSimulator.vue`, `ExchangeRateBadge.vue`
- `KycBanner.vue` — bannière de relance KYC
- `DocVerificationCapture.vue` — capture document KYC
- `ProjectCard.vue`, `MentorCard.vue`, `DashCard.vue`, `Field.vue`, `TagInput.vue`
- `RoleSwitcher.vue` — bascule active_role
- `LegalLayout.vue` — wrapper pages CGU/Confidentialité/DPA
- `DarkModeToggle.vue`

### 5.5 Composables et utilitaires

- `useKycApi.js` — wrapper Axios pour `/v1/kyc/*`
- `useSmileWebSdk.js` — chargement et orchestration de la Web SDK Smile (web token, callbacks)
- `useExchangeRate.js`, `useToast.js`
- `utils/currency.js`, `utils/smileResultCodes.js` (mapping codes 0810/0811/0812/1020/1022 → libellés FR)

---

## 6. Modèle de données

### 6.1 Schéma global (vue de haut)

**Référentiels & utilisateurs**
- `users` (+ Smile : `kyc_verified_at`, `kyc_expires_at`, `kyc_verification_id`, `aml_status`, `aml_last_checked_at`, `selfie_registered`; OAuth : `google_id`, `oauth_provider`)
- `roles`, `role_user`, `role_profiles` (JSON `data` + `completion`)
- `categories`, `sub_categories`
- `sdgs` (1..17 ONU), `project_sdg`
- `skills`, `skill_user` (pivot avec niveau + années)

**Projets / Crowdfunding**
- `projects` (+ payout_phone/provider/country pour DirectPay)
- `project_updates`, `project_followers`
- `investments` (+ liens PayDunya : transaction_id, paydunya_token, charged_amount/currency, paid_at, refunded_at)
- `escrow_milestones` (states `pending → in_review → approved → released | rejected`, +release_transaction_id)
- `reviews` (polymorphique `reviewable`)

**Mentorat**
- `mentorships` (+ skill_id, goals, duration_weeks, accepted_at, completed_at)
- `mentor_availabilities` (jour/heure récurrente)
- `mentorship_sessions`
- `mentor_reviews` (tags JSON)

**Emploi**
- `job_applications` (CV URL, scoring, notes internes entrepreneur)

**Gouvernement**
- `government_calls` (+ eligibility_criteria, evaluation_criteria, published_at, vues, candidatures count)
- `call_applications`
- `economic_zones` (ZES — incentives JSON, sectors JSON, area_hectares)

**Diaspora**
- `country_guides` (PIB, croissance, remittances %, ease_of_business, key_sectors, framework légal, taxation, incentives, risques, opportunités, programmes diaspora, agence investissement)
- `diaspora_simulations` (logs simulateur)

**Formalisation**
- `formalization_steps` (par pays, ordre, institution, documents, coût, durée, lien, tips)
- `formalization_progress` (user × step, statut, notes)
- `business_plan_templates` (sections JSON, freemium)
- `microfinance_partners`

**Abonnements & Paiements**
- `subscription_plans`, `subscriptions`
- `transactions` (polymorphique `transactable`, snapshot client, multi-gateway, installment_number/total, status complet)
- `payment_logs` (immutable, retention 5 ans — DPA §10 ; signature_valid, correlation_id)
- `installment_plans`, `installments` (polymorphique `payable` → Investment / Subscription / TrainingPurchase)

**Formations**
- `trainings`, `training_purchases`

**KYC / AML (Smile Identity)**
- `kyc_verifications` (smile_job_id, partner_job_id UUID, job_type, country, id_type, id_number_hash SHA-256+HMAC APP_KEY, result_code, confidence, actions JSON, kyc_level_granted, status, callback_payload JSON, expires_at)
- `aml_screenings` (full_name_screened, countries JSON, birth_year, sanctions/pep/adverse_media flags, risk_level low/medium/high/critical, auto_reported pour CENTIF)

**Publicité**
- `ad_banners` (impressions/clicks, position home_top/mid/sidebar, dates)
- `partners` (type institutional/financial/tech/ngo/government/media)
- `testimonials` (rating, featured, country)

**Notifications**
- `notifications` (Laravel DB channel — UUID, polymorphique notifiable)

**Infrastructure**
- `cache`, `cache_locks` (driver database)
- `jobs`, `failed_jobs`, `job_batches`
- `sessions`

### 6.2 Statistiques du modèle

- **35 modèles Eloquent** dans `app/Models`
- **32 migrations** datées d'avril 2026 à mai 2026
- 11 seeders thématiques

### 6.3 Conventions

- Identifiants : `bigint` auto-increment (sauf `notifications.id` UUID).
- Devises : `char(3)` ISO 4217. **EUR par défaut** sur les projets, **XOF** sur les transactions PayDunya.
- Décimaux monétaires : `decimal(14,2)` (élargi depuis `decimal(12,2)` par la migration `widen_money_columns_to_14_2`).
- Timestamps : `timestamps()` Laravel ; explicit `paid_at`, `refunded_at`, `expires_at`, `submitted_at`, `completed_at` quand pertinents.
- Relations polymorphiques : `transactable`, `payable`, `reviewable`, `notifiable`.
- JSON : `tags`, `gallery`, `actions`, `callback_payload`, `evidence`, `incentives`, `sectors`, `key_sectors`, `features`, `limits`, `required_documents`, `sections`, `matches`, …
- Indexation : tous les compositions `(user_id, status)`, `(project_id, status)`, `(gateway, event_type)`, `(country, status)` sont indexées pour les listings courants.

---

## 7. Services métier (logique applicative)

### 7.1 Couche Paiement (`app/Services/Payment`)

| Service | Lignes | Responsabilité |
|---|---:|---|
| **PaymentGatewayInterface** | 62 | Contrat unifié : `createCheckout`, `verifyPayment`, `refund`, `disburse`, `getExchangeRate`, `getName`. |
| **PaymentGatewayFactory** | — | Sélection du gateway par clé (paydunya | flutterwave | stripe | paypal). |
| **PayDunyaGateway** | — | Implémentation SDK PayDunya (CheckoutInvoice + DirectPay). |
| **CurrencyService** | 137 | Conversion EUR ↔ XOF/XAF (taux configurable + cache). |
| **InvestmentService** | 310 | Création investissement → checkout → webhook → escrow auto. |
| **SubscriptionService** | 334 | Cycle abonnement (subscribe, cancel, refund, verify). |
| **EscrowService** | 406 | Lifecycle jalons (submit/approve/reject/release/autoRefund). |
| **InstallmentService** | 301 | Plans fractionnés (création plan N installments + invoicing à échéance). |
| **TrainingService** | 206 | Achat formation + déverrouillage content_url. |
| **RefundService** | 202 | Remboursement (subscription / training / investment) avec garantie 30 jours. |

DTOs unifiés : `CheckoutResult`, `PaymentStatus`, `DisburseResult`, `RefundResult` (success/failure pattern).

### 7.2 Couche identité (`app/Services/SmileIdentity`)

- **SmileIdentityService** (494 lignes) — façade API REST Smile :
  - `submitBasicKYC()` → Job Type 5 (POST `/id_verification`)
  - `submitBiometricKYC()` → Job Type 1 (POST `/upload` + PUT S3)
  - `submitDocumentVerification()` → Job Type 6 (POST `/upload` + PUT S3)
  - `submitAMLCheck()` → Job Type 10 (POST `/aml_check`)
  - `generateWebToken()` (Hosted Web SDK)
  - `ensureConfigured()` — vérification lazy de `SMILE_PARTNER_ID`, `SMILE_API_KEY`, `base_url`.
- **SmileSignature** — HMAC-SHA256 base64 sur timestamp, comparaison `hash_equals`.

### 7.3 Jobs asynchrones

| Job | Déclencheur | Effet |
|---|---|---|
| `ProcessPayDunyaWebhook` | IPN PayDunya | Réconcilie la `transaction`, met à jour `subscription`/`investment`/`training_purchase`, déclenche escrow ou release. |
| `ProcessSmileCallback` | IPN Smile | Persiste callback_payload, met à jour `kyc_verifications`, met à jour user (`kyc_level`, `kyc_verified_at`, `kyc_expires_at`), émet events. |
| `ProcessEscrowRelease` | Approbation jalon | Appel DirectPay PayDunya → mise à jour milestone + transaction. |
| `ProcessInstallmentDue` | Cron 04:00 quotidien | Génère le prochain invoice pour chaque plan actif `next_due_at <= now()`. |
| `ProcessAutoRefund` | Cron 03:15 quotidien | Rembourse les investissements `escrow` non validés depuis >90 j (config `paydunya.disburse.auto_refund_days`). |
| `ExpireKYCVerification` | Cron 02:30 quotidien | Bascule en `expired` toute KYC dont `expires_at < now()` et auto-downgrade user → `basic`. |

### 7.4 Events & Listeners (Smile)

```
KYCVerified  → SendKYCNotification::handleKYCVerified  → Notification DB + email
             → UnlockKYCFeatures                       → MAJ user.kyc_level + features

KYCRejected  → SendKYCNotification::handleKYCRejected

AMLFlagged   → SendKYCNotification::handleAMLFlagged
             → ReportSuspiciousActivity                 → Auto-déclaration CENTIF (mockée, voir audit)
```

---

## 8. Sécurité

### 8.1 Middlewares applicatifs

Définis dans `app/Http/Middleware` et alias dans `bootstrap/app.php` :

| Alias | Classe | Usage | Particularités |
|---|---|---|---|
| `role` | `EnsureUserHasRole` | `role:government,admin` | Liste de rôles autorisés. |
| `subscribed` | `CheckSubscription` | `subscribed` ou `subscribed:pro,enterprise` | Plan free bloqué par défaut ; param pour cible précise. |
| `kyc` | `CheckKyc` | Legacy IDnorm | Conservé pour compatibilité. |
| `kyc.smile` | `RequireKYCLevel` | `kyc.smile:verified`, `kyc.smile:certified` | Auto-downgrade à expiration + retour `required_level`/`current_level` ; bloque si `aml_status == blocked`. |
| `aml.checked` | `RequireAmlCleared` | `aml.checked` | Exige une AML clear. |
| `paydunya.webhook` | `VerifyPayDunyaWebhook` | Webhook PayDunya | HMAC-SHA512. |
| `smile.webhook` | `VerifySmileSignature` | Webhook Smile | HMAC-SHA256 + replay window 300 s. |

### 8.2 Rate limiting (défini dans `AppServiceProvider::boot`)

| Bucket | Limite | Cible |
|---|---|---|
| `kyc-submissions` | 10/heure / user (ou IP) | POST /v1/kyc/{basic,biometric,document,web-token,consent} |
| `kyc-reads` | 60/min / user | GET /v1/kyc/{status,history} |
| `kyc-aml` | 3/heure / user | POST /v1/kyc/aml (très restrictif, API payante) |
| `admin-read` | 120/min / user | Lectures admin (anti-énumération) |
| `admin-write` | 60/heure / user | Mutations admin |
| `admin-upload` | 20/heure / user | Upload images admin (cap ~80 Mo/h) |
| `webhooks` | 200/min / IP | IPN PayDunya + Smile |
| `contact-form` | 5/heure / IP | Formulaire Enterprise |

### 8.3 Protection des PII

- **Numéros d'identité** : jamais en clair, hashés `hash_hmac('sha256', $idNumber, config('app.key'))` (`KYCVerification::hashIdNumber`) — `id_number_hash` indexé pour la recherche d'unicité.
- **Selfies / documents** : **jamais stockés** localement, seul `smile_job_id` est conservé ; URLs S3 signées de Smile consultées à la demande.
- **Champs `users` sensibles cachés par défaut** (`$hidden`) : `password`, `remember_token`, `phone`, `email_verified_at`, `aml_status`, `aml_last_checked_at`, `kyc_verification_id`, `selfie_registered` — la constante `User::SELF_VISIBLE` les rend visibles uniquement sur le `/me` propriétaire et les vues admin (`makeVisible([...])`).
- **`callback_payload` Smile** caché des sérialisations API par défaut.
- **`payment_logs`** : audit trail immutable, signature_valid, retention 5 ans (DPA §10).
- **Helper `PiiRedactor`** pour anonymiser les logs.

### 8.4 Sécurité réseau

- **Nginx** : HSTS 1 an `includeSubDomains`, X-Frame-Options SAMEORIGIN, X-Content-Type-Options nosniff, Referrer-Policy strict-origin-when-cross-origin, Permissions-Policy bloquant geo/mic/camera, deny des fichiers `.env`/`.log`/`.sql`/`.md`/`.lock`/`.htaccess`.
- **TLS** : Let's Encrypt, TLSv1.2/1.3 uniquement, cache session 10 m.
- **CSRF** : actif par défaut sur toutes les routes web ; exempté pour les 3 webhooks (`api/v1/webhooks/paydunya`, `api/webhooks/paydunya`, `api/v1/webhooks/smile-identity`).
- **Webhook Smile** : replay window 5 min + clock skew 1 min + `hash_equals` (timing-safe).

### 8.5 Authentification & autorisations

- **Session-based** (`web` guard) — cookie + table `sessions`.
- **Mot de passe** : bcrypt 12 rounds.
- **Google OAuth** : Socialite, scopes `openid profile email`, callback redirige sur `/dashboard?oauth=google`. 3 stratégies : par google_id, par email (link), création.
- **Policies** : `ProjectPolicy` (gate enregistré via `Gate::policy` dans `AppServiceProvider`).

### 8.6 Audit Smile Identity

Document de référence : `docs/smile-identity/security-audit.md`. Synthèse :

| Domaine | Statut | Risque résiduel |
|---|---|---|
| Authentification webhook HMAC | OK | Faible |
| Idempotence callbacks | OK (double garde) | Faible |
| Stockage PII (id_number) | OK (hash HMAC-SHA256) | Faible |
| Stockage biométriques | OK (jamais local) | Faible |
| Secrets configuration | OK | Faible |
| CSRF webhook | OK | Nul |
| Rate-limit `/v1/kyc/*` | OK depuis le fix | Faible |
| Audit trail LCB-FT | OK (`payment_logs`) | Faible |
| Auto-blocage sanctions | OK | Faible |
| Déclaration CENTIF | **Mockée** (non branchée) | **Moyen** |
| RGPD — droit d'accès / oubli | Pas d'export dédié | **Moyen** |
| TLS callback URL | OK (HTTPS only en prod) | Faible |
| Renouvellement 24 mois | OK (cron + downgrade) | Faible |

---

## 9. Intégrations externes

### 9.1 PayDunya (paiement / disbursement)

- **SDK** : `paydunya/paydunya:^1.0` (composer).
- **Configuration** : `config/paydunya.php` :
  - Credentials : `master_key`, `private_key`, `public_key`, `token` (mode `test`/`live`).
  - Frais plateforme : 3 % / 2 % / 1 % (tier1 < 100k XOF, tier2 < 1M, tier3 >= 1M).
  - Devises supportées : XOF, XAF, EUR, USD.
  - UEMOA : SN, CI, ML, BF, TG, BJ, NE, GW. CEMAC : CM, CF, TD, CG, GQ, GA.
  - 16 canaux mobile-money + carte (Orange Money / Wave / Free Money / Moov / MTN / T-Money + cards).
  - Disbursement : min 500 XOF, max 5 M XOF, auto-refund 90 j.
  - Webhook secret : HMAC-SHA512.
- **URLs** : auto-réécriture en local vers `APP_URL` (dev), valeurs env en prod.

### 9.2 Smile Identity (eKYC + AML)

- **Configuration** : `config/smile.php` :
  - Sandbox : `https://testapi.smileidentity.com/v1`, Prod : `https://api.smileidentity.com/v1`.
  - Job types : 1 (biometric), 5 (basic), 6 (document), 10 (AML).
  - SDK : nom `rest_api` version `2.0.0` (épinglée pour télémétrie Smile).
  - HTTP : timeout 15 s, retry 2x, sleep 500 ms.
  - Validité KYC : 24 mois.
- **Web SDK** : iframe hosted, web token généré par `/v1/kyc/web-token`, orchestré côté SPA par `useSmileWebSdk.js`.
- **Couverture pays** : tous les pays UEMOA + CEMAC (CNI, NINA, NIN, BVN, passeport, permis).

### 9.3 Google OAuth

- **Driver Socialite** `google` (`config/services.php`).
- Variables : `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_CALLBACK_URL`.
- Scopes : `openid profile email`.

### 9.4 Mail

- Driver `log` en local ; production : SMTP configurable (`MAIL_*`).
- Mail : `App\Mail\ContactInquiry` (formulaire Enterprise sur `/tarifs`).
- Adresse contact destinataire : config `contact.php`.

---

## 10. Conformité réglementaire

### 10.1 Cadres applicables

| Cadre | Domaine | Implémentation |
|---|---|---|
| **UEMOA Directive 02/2015/CM** Art. 18 | LCB-FT — KYC investisseur obligatoire | `kyc.smile:verified` + `aml.checked` sur `POST /investments` et toutes actions financières. |
| **RGPD Art. 9.2.a** | Consentement explicite données biométriques | Endpoint `POST /v1/kyc/consent` qui journalise le consentement avant capture biométrique. |
| **CENTIF** | Déclaration de soupçon | Listener `ReportSuspiciousActivity` déclenché par `AMLFlagged` (à brancher à la vraie API CENTIF — actuellement mocké). |
| **DPA §10** | Retention audit financier | `payment_logs` conservé 5 ans. |
| **RGPD Art. 15/17** | Droit d'accès / oubli | À implémenter (audit identifie le gap). |

### 10.2 Pages légales SPA

- `/cgu` — Conditions Générales d'Utilisation
- `/confidentialite` — Politique de confidentialité
- `/dpa` — Data Processing Agreement

---

## 11. Infrastructure & déploiement

### 11.1 Environnements

| Env | URL | Mode PayDunya | Mode Smile |
|---|---|---|---|
| Local dev | `http://127.0.0.1:8000` (`php artisan serve`) | `test` | `sandbox` |
| Production | `https://globalafricaplus.com` | `live` (à activer) | `production` (à activer) |

### 11.2 Provisionnement

- Script `deploy/provision.sh` — bootstrap initial du VPS (Ubuntu).
- Nginx vhost `deploy/nginx.conf` — TLS Let's Encrypt + headers sécurité + cache assets.
- Supervisor `deploy/supervisor-worker.conf` — daemon `php artisan queue:work`.

### 11.3 Déploiement atomique

Script `deploy/deploy.sh` (invoqué par CI/CD sur push `main`) :

1. Extrait le tarball `release-<sha>.tar.gz` dans `releases/{timestamp}_{sha8}`.
2. Symlinks vers `shared/.env`, `shared/storage`, `shared/bootstrap/cache`.
3. Fixe permissions www-data 2775/0664.
4. Génère `APP_KEY` si manquant.
5. `package:discover` + `config:cache` + `route:cache` + `view:cache`.
6. `php artisan migrate --force`.
7. Switch symlink `current` → nouveau release.
8. `php-fpm reload` + queue restart.
9. Conserve les 5 derniers releases (`KEEP_RELEASES=5`).

### 11.4 CI/CD

- `.github/workflows/` — GitHub Actions (build, tests, déploiement SSH).
- Tests automatiques : PHPUnit (`composer test`).
- Lint : Laravel Pint (`laravel/pint`).

### 11.5 Scheduled tasks (cron Laravel)

Définis dans `bootstrap/app.php::withSchedule` :

| Tâche | Heure | Job |
|---|---|---|
| `escrow:auto-refund` | Quotidien 03:15 | `ProcessAutoRefund` |
| `installments:process-due` | Quotidien 04:00 | `ProcessInstallmentDue` |
| `kyc:expire-verifications` | Quotidien 02:30 | `ExpireKYCVerification` |

Toutes avec `withoutOverlapping()` et `onOneServer()`.

---

## 12. Qualité & tests

### 12.1 Suite de tests

- **PHPUnit 11** — entrée `phpunit.xml`.
- **Composer scripts** : `composer test` → `config:clear` + `artisan test`.

### 12.2 Couverture

| Domaine | Fichier | Objet |
|---|---|---|
| OAuth | `GoogleOAuthTest` | Flow Socialite — login, link email, création. |
| Contact | `ContactInquiryTest` | Formulaire Enterprise (rate-limit, mail). |
| Smile — Signature | `SignatureTest` | 6 cas `generate()`/`confirm()`. |
| Smile — Webhook HTTP | `SmileWebhookHttpTest` | 4 cas (tamper, missing sig/ts, exempt CSRF). |
| Smile — Idempotence | `WebhookIdempotenceTest` | Event `KYCVerified` dispatché 1× seulement. |
| Smile — Replay window | `WebhookReplayWindowTest` | Refus si timestamp > 5 min. |
| Smile — Middleware | `MiddlewareTest`, `RouteKYCGateTest` | Auto-downgrade, gates routes. |
| Smile — AML | `AmlCheckTest`, `ReportSuspiciousActivityTest` | Match PEP/sanctions, auto-report. |
| Smile — Rate limit | `RateLimitTest` | Buckets kyc-submissions/aml. |
| Smile — Expire | `ExpireKYCTest` | Job quotidien 02:30. |
| Smile — Admin | `AdminKycOverrideTest` | Override manuel admin. |
| Smile — PII | `PiiRedactorTest`, `UserHiddenFieldsTest` | Anonymisation logs + champs cachés. |
| Smile — Migration legacy | `MigrateLegacyKycSessionsTest` | Commande `kyc:migrate-sessions`. |

### 12.3 Outillage

- **Laravel Pail** (`laravel/pail`) — tail logs en dev.
- **Laravel Sail** (`laravel/sail`) — Docker dev (non utilisé en local Windows actuellement, XAMPP).
- **Mockery** — pour mocks PHPUnit.
- **Faker FR** (`fakerphp/faker`, `APP_FAKER_LOCALE=fr_FR`).

---

## 13. Forces & limites de l'existant

### 13.1 Forces

1. **Cohérence Laravel/Vue** : architecture canonique, peu de dette technique structurelle.
2. **Modularité fonctionnelle** : 10 modules métier clairement délimités, mais partageant les mêmes briques transverses (paiement, KYC, abonnement).
3. **Sécurité défense en profondeur** : rate-limit + HMAC + idempotence + champs `$hidden` + replay window — l'audit Smile note "risque résiduel faible" sur la majorité des domaines.
4. **Conformité réglementaire native** : KYC tiered (basic/verified/certified) + AML + audit trail LCB-FT structurel, pas un add-on tardif.
5. **Modèle de données soigné** : indexes composites, décimaux 14,2, polymorphismes corrects, ENUMs explicites.
6. **Déploiement atomique** : zero-downtime via symlinks, rollback trivial (5 releases conservés).
7. **Multi-devises & multi-pays** : EUR/XOF/XAF/USD + 14 pays UEMOA/CEMAC supportés nativement par PayDunya.

### 13.2 Points d'attention

1. **Monolithe** : pas de découpage en micro-services. Acceptable au stade actuel mais limitera la scalabilité horizontale au-delà d'un seuil.
2. **Pas de cache distribué** : Redis configuré dans `.env` mais `CACHE_STORE=database` et `QUEUE_CONNECTION=database` en pratique — performances à surveiller en charge.
3. **Pas de file de messages externe** : queues `database`, OK pour démarrage mais à migrer vers Redis/SQS si volumétrie.
4. **CENTIF mockée** — la déclaration de soupçon est journalisée mais non transmise à la cellule réelle.
5. **RGPD droit d'accès/oubli** : pas d'export utilisateur dédié — gap identifié par l'audit.
6. **Pas de Sanctum API tokens** : l'API n'est pas exploitable hors session (volonté assumée pour le SPA, mais bloque la promesse "API dédiée" du plan Enterprise).
7. **Tests** : couverture forte sur Smile Identity, plus modeste sur paiement / escrow / mentorat / formalisation. Pas de tests E2E SPA (Cypress/Playwright).
8. **Pas de monitoring applicatif outillé** : pas de Sentry/Bugsnag détecté dans les configs.
9. **`.env` versionné contient des clés secrets de test** — bonne pratique : tourner les secrets sandbox visibles à la rotation.

---

## 14. Lexique

| Terme | Définition |
|---|---|
| AML | Anti-Money Laundering — Lutte contre le blanchiment. |
| CENTIF | Cellule Nationale de Traitement des Informations Financières (Sénégal). |
| CEMAC | Communauté Économique et Monétaire de l'Afrique Centrale (devise XAF). |
| eKYC | Electronic Know-Your-Customer. |
| Escrow | Mécanisme de séquestre des fonds, libéré par jalons. |
| IPN | Instant Payment Notification (webhook PayDunya). |
| LCB-FT | Lutte contre le Blanchiment de Capitaux et le Financement du Terrorisme. |
| PEP | Politically Exposed Person. |
| RBAC | Role-Based Access Control. |
| RGPD | Règlement Général sur la Protection des Données. |
| SPA | Single Page Application. |
| UEMOA | Union Économique et Monétaire Ouest Africaine (devise XOF). |
| XAF / XOF | Franc CFA central / Franc CFA ouest. |
| ZES | Zone Économique Spéciale. |

---

## 15. Références internes

| Document | Chemin | Objet |
|---|---|---|
| Spec Smile Identity | `Globalafrica_Specification_SmileIdentity_v1.docx` | Référence du sprint d'intégration. |
| Audit sécurité Smile | `docs/smile-identity/security-audit.md` | Synthèse risques. |
| API Smile | `docs/smile-identity/api-reference.md` | Référence endpoints. |
| Réponses API génériques | `docs/API_RESPONSES.md` | Conventions réponses JSON. |
| Spec PayDunya | `paydunya_spec.txt` | Référence du sprint paiement. |
| Config déploiement | `deploy/README.md` | Procédure VPS. |

---

*Fin du dossier d'architecture.*
