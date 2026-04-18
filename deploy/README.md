# Déploiement GlobalAfrica+ — VPS Hostinger

Pipeline CI/CD complète : **push sur `main` → tests → build → déploiement atomique sur VPS**.

---

## Architecture

```
/var/www/globalafricaplus.com/
├── current         → symlink vers la release active
├── releases/
│   ├── 20260418120000_a1b2c3d4/
│   ├── 20260418130000_e5f6a7b8/
│   └── …           (5 dernières conservées)
└── shared/
    ├── .env        (secrets de production — jamais versionnés)
    ├── storage/    (uploads, logs, cache)
    └── bootstrap/cache/
```

Le déploiement est **atomique** : un `mv -Tf` sur le symlink `current` bascule instantanément vers la nouvelle release. Zero downtime.

---

## Stack côté VPS

- **OS** : Ubuntu 22.04 LTS
- **Web** : Nginx
- **PHP** : 8.2-FPM (opcache activé)
- **DB** : MySQL 8
- **Cache/Queue** : Redis
- **Workers** : Supervisor (2 process queue:work)
- **SSL** : Let's Encrypt (certbot auto-renew)
- **Firewall** : UFW + fail2ban

---

## 🚀 Setup initial (à faire UNE fois)

### A. Sur votre VPS Hostinger (en `root`)

```bash
# 1) Récupérez les scripts depuis le repo
cd /tmp
wget https://raw.githubusercontent.com/oksam90/globalafricaplus-web/main/deploy/provision.sh
wget https://raw.githubusercontent.com/oksam90/globalafricaplus-web/main/deploy/nginx.conf
wget https://raw.githubusercontent.com/oksam90/globalafricaplus-web/main/deploy/supervisor-worker.conf

# 2) Lancez le provisioning
chmod +x provision.sh
bash provision.sh
```

Le script installe LEMP + Redis + Supervisor + Certbot, crée la DB, l'utilisateur `deploy`, l'arborescence `/var/www/globalafricaplus.com/`, et configure le firewall.

### B. Générer une paire de clés SSH pour le CI/CD

Sur votre machine locale :

```bash
ssh-keygen -t ed25519 -f ga_deploy_key -C "ci@globalafricaplus"
# Ne mettez PAS de passphrase
```

- Copiez le contenu de `ga_deploy_key.pub` dans `/home/deploy/.ssh/authorized_keys` sur le VPS
- Gardez `ga_deploy_key` (clé privée) pour l'étape C

### C. DNS Hostinger

Dans le panel Hostinger, pour `globalafricaplus.com` :

| Type  | Nom | Valeur              | TTL  |
|-------|-----|---------------------|------|
| A     | @   | IP_DE_VOTRE_VPS     | 3600 |
| A     | www | IP_DE_VOTRE_VPS     | 3600 |

Attendez 5–15 minutes la propagation, puis testez : `ping globalafricaplus.com`

### D. Activer HTTPS (sur le VPS)

```bash
certbot --nginx -d globalafricaplus.com -d www.globalafricaplus.com \
  --non-interactive --agree-tos -m admin@globalafricaplus.com
```

### E. Compléter le `.env` de production

```bash
sudo nano /var/www/globalafricaplus.com/shared/.env
```

Remplacez tous les `CHANGE_ME` :
- `DB_PASSWORD` → celui affiché à la fin du script provision.sh
- `MAIL_*` → SMTP Hostinger
- `IDNORM_*` → credentials production IDNorm

### F. Secrets GitHub

Dans **GitHub → Settings → Secrets and variables → Actions** du repo `oksam90/globalafricaplus-web`, ajoutez :

| Secret              | Valeur                                 |
|---------------------|----------------------------------------|
| `SSH_HOST`          | IP publique du VPS                     |
| `SSH_PORT`          | `22` (ou votre port SSH custom)        |
| `SSH_USER`          | `deploy`                               |
| `SSH_PRIVATE_KEY`   | contenu de `ga_deploy_key` (clé privée)|

Créez aussi l'environment `production` dans **Settings → Environments** (optionnel : reviewers manuels).

---

## 🔄 Déploiement continu

À partir de maintenant :

```bash
git add .
git commit -m "feat: nouvelle fonctionnalité"
git push origin main
```

Le workflow `.github/workflows/deploy.yml` se déclenche et :

1. **Tests & build** sur runner GitHub :
   - `composer install --no-dev`
   - PHP lint
   - `npm ci && npm run build` (Vite)
   - Crée un tarball
2. **Deploy** sur le VPS via SSH :
   - Upload du tarball
   - Extraction dans `releases/YYYYMMDDHHMMSS_sha/`
   - Symlinks vers `shared/` (`.env`, `storage/`, `bootstrap/cache`)
   - `artisan config:cache route:cache view:cache`
   - `artisan migrate --force`
   - `mv -Tf` atomique du symlink `current`
   - Reload PHP-FPM + restart queue workers
   - Cleanup anciennes releases (garde les 5 dernières)
3. **Smoke test** : `curl https://globalafricaplus.com`

---

## Rollback rapide

Si un déploiement casse quelque chose, sur le VPS :

```bash
cd /var/www/globalafricaplus.com/releases
ls -1tr                              # liste des releases
ln -sfn releases/PREVIOUS ../current  # pointe vers la release précédente
sudo systemctl reload php8.2-fpm
```

---

## Opérations courantes

### Logs
```bash
tail -f /var/www/globalafricaplus.com/shared/storage/logs/laravel.log
tail -f /var/log/nginx/globalafricaplus.error.log
sudo journalctl -u php8.2-fpm -f
```

### Queue worker
```bash
sudo supervisorctl status
sudo supervisorctl restart globalafricaplus-worker:*
```

### Artisan
```bash
cd /var/www/globalafricaplus.com/current
php artisan tinker
php artisan queue:failed
```

### Backup DB (cron quotidien recommandé)
```bash
mysqldump -u gapuser -p globalafricaplus | gzip > /backup/gap_$(date +%F).sql.gz
```

---

## Checklist avant le premier push

- [ ] `provision.sh` exécuté sur le VPS
- [ ] Clé publique CI ajoutée dans `/home/deploy/.ssh/authorized_keys`
- [ ] DNS A records pointés vers l'IP du VPS
- [ ] Certbot a généré les certificats SSL
- [ ] `/var/www/globalafricaplus.com/shared/.env` complété (DB, mail, IDNorm)
- [ ] 4 secrets GitHub Actions configurés (`SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`)
- [ ] Push sur `main` → vérifier l'onglet **Actions** du repo

✅ Une fois coché, tout `git push` sur `main` déploie automatiquement.
