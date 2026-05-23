# Conventions de réponse JSON — API Globalafrica+

> **Statut** : référence vivante. Audit 2026-05.
> **Public** : devs backend et frontend.
> **But** : éviter qu'un nouveau endpoint multiplie les formats. Le code existant
> reste tel quel par compatibilité SPA, mais **tout nouveau endpoint doit suivre
> les conventions ci-dessous**.

---

## TL;DR — Cinq cas, cinq formes

| Cas | Forme | Status HTTP | Exemple route |
|---|---|---|---|
| Single resource (GET) | `{ "data": <object> }` | 200 | `GET /api/sectors/{slug}` |
| Collection paginée | `<paginator natif Laravel>` (top-level `data`, `meta`, `links`) | 200 | `GET /api/admin/users` |
| Collection non paginée | `{ "data": [<array>] }` | 200 | `GET /api/subscription/plans` |
| Mutation (POST / PATCH / DELETE) | `{ "message": "...", "data": <object>? }` | 200 / 201 | `POST /api/admin/sectors` |
| Erreur | `{ "message": "...", "errors"?: {...} }` (Laravel default) | 4xx / 5xx | partout |

Tout endpoint **composé** (dashboard, analytics, formulaire multi-resource) garde
des clés métier explicites au top-level — voir [§ 6](#6-réponses-composées).

---

## 1. Single resource

```http
GET /api/sectors/agritech
200 OK
{
  "data": {
    "id": 3,
    "slug": "agritech",
    "name": "Agritech",
    ...
  },
  "stats": { ... },        // OK : champ adjacent au resource principal
  "top_projects": [ ... ]  // idem
}
```

**Règle** : la ressource principale est sous `data`. Les enrichissements
contextuels (stats, related, breakdown) peuvent vivre au top-level **uniquement
si l'endpoint est explicitement un endpoint de vue composée** (sector show,
country guide show, project show). Pour un endpoint REST classique, ne mettre
que `data`.

## 2. Collection paginée

Laravel retourne nativement :

```http
GET /api/admin/users?page=2
200 OK
{
  "data": [ ... ],
  "current_page": 2,
  "first_page_url": "...",
  "from": 21,
  "last_page": 5,
  "last_page_url": "...",
  "links": [ { url, label, active }, ... ],
  "next_page_url": null,
  "path": "...",
  "per_page": 20,
  "prev_page_url": "...",
  "to": 40,
  "total": 100
}
```

**Règle** : renvoyer `response()->json($query->paginate(...))` **sans wrapper**.
Le SPA consomme `data.data` (la collection), `data.last_page`, `data.total`.

**Ne pas** wrapper dans un second `data` :

```php
// ❌ Mauvais — la SPA verrait data.data.data
return response()->json(['data' => $query->paginate()]);

// ✅ Bon
return response()->json($query->paginate());
```

## 3. Collection non paginée

```http
GET /api/subscription/plans
200 OK
{
  "data": [ { ... }, { ... } ]
}
```

**Règle** : wrapper sous `data`, même si la collection est petite et stable.
Préserve la forward-compat (ajout futur de `meta` / `links`).

## 4. Mutations (POST / PATCH / DELETE)

```http
POST /api/admin/sectors
201 Created
{
  "message": "Secteur « Agritech » créé.",
  "data": { ... resource ... }
}
```

```http
PATCH /api/admin/sectors/3
200 OK
{
  "message": "Secteur « Agritech » mis à jour.",
  "data": { ... resource ... }
}
```

```http
DELETE /api/admin/sectors/3
200 OK
{
  "message": "Secteur « Agritech » supprimé.",
  "id": 3                                // optionnel — quand l'objet est mort
}
```

**Règle** :
- Toujours un `message` FR utilisateur (affichable en toast).
- `data` quand la ressource existe encore (création, update).
- `id` pour DELETE si la SPA veut filtrer sa liste locale sans refetch.

### 4.1 Mutation avec garde (force, confirm)

Quand la mutation refuse pour cause de cascade non-vide :

```http
DELETE /api/admin/sectors/3
422 Unprocessable Entity
{
  "message": "Ce secteur contient 5 projet(s). Passez `force: true` pour confirmer (les projets seront détachés).",
  "projects_count": 5,
  "requires_force": true
}
```

**Règle** : code HTTP `422`, `message` actionable, flag booléen `requires_*`
en top-level pour que la SPA puisse afficher un dialogue de confirmation
typé. Re-tenter avec `?force=true` ou `{ "force": true }`.

## 5. Erreurs

### 5.1 Validation (Laravel automatique)

```http
422 Unprocessable Entity
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["Le nom est obligatoire."],
    "slug": ["Le slug est déjà utilisé."]
  }
}
```

→ La SPA extrait `Object.values(errors)[0]?.[0]` comme premier message
d'erreur.

### 5.2 Erreurs métier (lancées manuellement)

```http
422 Unprocessable Entity
{
  "message": "Aucun abonnement payant à annuler."
}
```

### 5.3 Erreurs d'autorisation typées (middleware)

Les middlewares applicatifs renvoient des codes structurés :

```http
403 Forbidden
{
  "error": "kyc_insufficient",
  "required_level": "verified",
  "current_level": "basic",
  "message": "Veuillez compléter votre vérification KYC."
}
```

```http
403 Forbidden
{
  "message": "Cette fonctionnalité nécessite un abonnement supérieur.",
  "subscription_required": true,
  "required_plans": ["pro", "enterprise"],
  "current_plan": "starter"
}
```

**Règle** : préfixe d'erreur structurée :
- `error` (slug snake_case) pour distinguer les sous-cas côté frontend
- `message` (FR utilisateur)
- champs additionnels typés (`required_*`, `current_*`) pour la déduction UI

Codes connus :
- `auth_required` (401)
- `kyc_required`, `kyc_insufficient`, `kyc_expired` (403)
- `aml_required`, `aml_flagged`, `aml_blocked` (403)
- `subscription_required` (403)

## 6. Réponses composées

Certains endpoints servent une vue d'ensemble qui n'a pas de "ressource
principale" — dashboard admin, analytics, page d'accueil d'un module. Ces
endpoints peuvent légitimement renvoyer plusieurs blocs nommés au top-level.

```http
GET /api/admin/analytics
200 OK
{
  "kpis": { total_users, total_projects, ... },
  "users_by_role": [ ... ],
  "projects_by_country": [ ... ],
  "registration_trend": [ ... ]
}
```

**Règle** : tolérés **uniquement** pour les endpoints clairement vue-composée
(`/dashboard`, `/analytics`, `/stats`, certains `/{module}/{country}` du
Diaspora). Pour le reste, retomber sur la forme single-resource avec
adjacents (§ 1).

## 7. Compatibilité endpoints existants

Le code existant utilise parfois des clés métier ad-hoc qu'on **ne casse pas** :

| Endpoint | Forme actuelle | Pourquoi pas refacto |
|---|---|---|
| `GET /api/auth/me` | `{ user, subscription, kyc }` | Vue composée légitime (§ 6) |
| `POST /api/auth/login` | `{ user, message? }` | Idem |
| `POST /api/projects/{id}/follow` | `{ is_following: true }` | Action simple, pas de resource à retourner |
| `POST /api/contact` | `{ received: true }` | Idem |

→ Pour ces endpoints, **ne pas changer le contrat** — la SPA destructure
ces clés en dur.

## 8. Status codes

| Code | Quand l'utiliser |
|---|---|
| 200 | Lecture OK, mutation OK (PATCH, DELETE, action) |
| 201 | Création OK (POST qui crée une nouvelle row) |
| 202 | Action acceptée mais traitée en async (webhook → job dispatché) |
| 204 | Mutation OK sans payload de retour (rare ici, on préfère 200 + message) |
| 400 | Mauvaise requête générique |
| 401 | Pas d'authentification (auth middleware) |
| 403 | Authentifié mais pas autorisé (role, kyc, aml, subscription) |
| 404 | Ressource introuvable (Eloquent `firstOrFail`) |
| 422 | Validation échouée OU règle métier refuse (cancel non éligible, force requis) |
| 429 | Rate-limit dépassé (throttle middleware) |
| 500 | Erreur serveur — toujours logger côté Laravel |

## 9. Checklist pour un nouveau endpoint

Avant de merger un nouveau contrôleur API :

- [ ] Single resource → `['data' => $obj]`
- [ ] Collection paginée → `$query->paginate()` direct (pas de wrapper)
- [ ] Collection non paginée → `['data' => $array]`
- [ ] Mutation → `['message' => 'FR utilisateur', 'data' => $obj?]`
- [ ] Erreur métier → code 4xx + `['message' => 'FR utilisateur']` (+ `error` slug pour middleware)
- [ ] Garde `force`/`confirm` → 422 + `requires_*: true`
- [ ] Mutation sensible → `Log::info|warning('admin.<resource>.<action>', [...])`
- [ ] Tests : au moins un cas success + un cas erreur validation + un cas autorisation
