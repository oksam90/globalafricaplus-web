#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
# GlobalAfrica+ — Atomic deployment script
# Exécuté sur le VPS par le workflow CI/CD
# Usage: deploy.sh <git-sha>
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

SHA="${1:-unknown}"
APP_PATH="/var/www/globalafricaplus.com"
RELEASES_PATH="${APP_PATH}/releases"
SHARED_PATH="${APP_PATH}/shared"
CURRENT_LINK="${APP_PATH}/current"
TARBALL="/tmp/release-${SHA}.tar.gz"
KEEP_RELEASES=5

TIMESTAMP=$(date +%Y%m%d%H%M%S)
NEW_RELEASE="${RELEASES_PATH}/${TIMESTAMP}_${SHA:0:8}"

echo "▶ Deploying ${SHA} → ${NEW_RELEASE}"

# ─── 1. Extraire le release ────────────────────────────
mkdir -p "${NEW_RELEASE}"
tar -xzf "${TARBALL}" -C "${NEW_RELEASE}"
rm -f "${TARBALL}"

cd "${NEW_RELEASE}"

# ─── 2. Lier les ressources partagées ──────────────────
# .env
ln -sfn "${SHARED_PATH}/.env" "${NEW_RELEASE}/.env"

# storage
rm -rf "${NEW_RELEASE}/storage"
ln -sfn "${SHARED_PATH}/storage" "${NEW_RELEASE}/storage"

# bootstrap/cache (peut être recréé mais on partage pour stabilité)
rm -rf "${NEW_RELEASE}/bootstrap/cache"
ln -sfn "${SHARED_PATH}/bootstrap/cache" "${NEW_RELEASE}/bootstrap/cache"

# ─── 3. Permissions ────────────────────────────────────
chown -R "$(whoami)":www-data "${NEW_RELEASE}"
find "${NEW_RELEASE}" -type d -exec chmod 2775 {} \;
find "${NEW_RELEASE}" -type f -exec chmod 0664 {} \;
chmod -R ug+rwX "${SHARED_PATH}/storage" "${SHARED_PATH}/bootstrap/cache" 2>/dev/null || true

# ─── 4. APP_KEY si absent ──────────────────────────────
if ! grep -q "^APP_KEY=base64:" "${SHARED_PATH}/.env"; then
  echo "▶ Generating APP_KEY…"
  php artisan key:generate --force --ansi
fi

# ─── 5. Cache Laravel ──────────────────────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

# ─── 6. Storage symlink (public/storage) ───────────────
php artisan storage:link || true

# ─── 7. Migrations (non destructif, on ne seed pas) ────
php artisan migrate --force --no-interaction

# ─── 8. SWITCH atomique ────────────────────────────────
ln -sfn "${NEW_RELEASE}" "${CURRENT_LINK}.new"
mv -Tf "${CURRENT_LINK}.new" "${CURRENT_LINK}"
echo "✔ Symlink ${CURRENT_LINK} → ${NEW_RELEASE}"

# ─── 9. Reload PHP-FPM + queue workers ────────────────
sudo /bin/systemctl reload php8.2-fpm
sudo /usr/bin/supervisorctl restart globalafricaplus-worker:* 2>/dev/null || true

# ─── 10. Nettoyage anciens releases ────────────────────
cd "${RELEASES_PATH}"
ls -1tr | head -n -${KEEP_RELEASES} | xargs -r rm -rf
echo "✔ Kept last ${KEEP_RELEASES} releases"

echo "✅ Deployment complete — ${SHA:0:8}"
