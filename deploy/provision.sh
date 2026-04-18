#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
# GlobalAfrica+ — Provisioning VPS Hostinger (Ubuntu 22.04)
# À exécuter UNE seule fois sur un VPS fraîchement installé
# Usage (en root):  bash provision.sh
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

DOMAIN="globalafricaplus.com"
APP_USER="deploy"
APP_PATH="/var/www/${DOMAIN}"
DB_NAME="globalafricaplus"
DB_USER="gapuser"
PHP_VERSION="8.2"

echo ">>> [1/10] Mise à jour système"
apt-get update -y
apt-get upgrade -y
apt-get install -y software-properties-common curl unzip git ca-certificates \
                   ufw fail2ban supervisor certbot python3-certbot-nginx

echo ">>> [2/10] Pare-feu UFW"
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

echo ">>> [3/10] PHP ${PHP_VERSION}"
add-apt-repository ppa:ondrej/php -y
apt-get update -y
apt-get install -y php${PHP_VERSION}-fpm php${PHP_VERSION}-cli \
  php${PHP_VERSION}-mysql php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml \
  php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-bcmath \
  php${PHP_VERSION}-intl php${PHP_VERSION}-gd php${PHP_VERSION}-redis \
  php${PHP_VERSION}-opcache

echo ">>> [4/10] Composer"
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

echo ">>> [5/10] Nginx & MySQL & Redis"
apt-get install -y nginx mysql-server redis-server

echo ">>> [6/10] Node.js 20 (pour builds locaux éventuels)"
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y nodejs

echo ">>> [7/10] Utilisateur de déploiement"
if ! id -u "${APP_USER}" >/dev/null 2>&1; then
  adduser --disabled-password --gecos "" "${APP_USER}"
  usermod -aG www-data "${APP_USER}"
fi

mkdir -p /home/${APP_USER}/.ssh
chmod 700 /home/${APP_USER}/.ssh
touch /home/${APP_USER}/.ssh/authorized_keys
chmod 600 /home/${APP_USER}/.ssh/authorized_keys
chown -R ${APP_USER}:${APP_USER} /home/${APP_USER}/.ssh

echo "-> AJOUTEZ la clé publique CI dans /home/${APP_USER}/.ssh/authorized_keys"

echo ">>> [8/10] Arborescence application"
mkdir -p ${APP_PATH}/{releases,shared/storage/{app,framework/{cache,sessions,views},logs}}
mkdir -p ${APP_PATH}/shared/bootstrap/cache

# Placeholder .env
if [ ! -f "${APP_PATH}/shared/.env" ]; then
  cat > ${APP_PATH}/shared/.env <<'EOF'
APP_NAME="GlobalAfrica+"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://globalafricaplus.com

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=globalafricaplus
DB_USERNAME=gapuser
DB_PASSWORD=CHANGE_ME

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=no-reply@globalafricaplus.com
MAIL_PASSWORD=CHANGE_ME
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=no-reply@globalafricaplus.com
MAIL_FROM_NAME="GlobalAfrica+"

IDNORM_MODE=prod
IDNORM_API_KEY=CHANGE_ME
IDNORM_API_SECRET=CHANGE_ME
IDNORM_WEBHOOK_SECRET=CHANGE_ME
EOF
  chmod 640 ${APP_PATH}/shared/.env
fi

chown -R ${APP_USER}:www-data ${APP_PATH}
find ${APP_PATH} -type d -exec chmod 2775 {} \;
find ${APP_PATH} -type f -exec chmod 0664 {} \;

echo ">>> [9/10] Base de données"
DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"
mysql --execute="CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql --execute="CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql --execute="GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql --execute="FLUSH PRIVILEGES;"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   DB_PASSWORD généré: ${DB_PASS}"
echo "   Éditez ${APP_PATH}/shared/.env et remplacez CHANGE_ME par ce mot de passe"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

echo ">>> [10/10] Nginx & SSL"
cp /tmp/nginx.conf /etc/nginx/sites-available/${DOMAIN} 2>/dev/null || \
  echo "-> Copiez deploy/nginx.conf vers /etc/nginx/sites-available/${DOMAIN}"
ln -sf /etc/nginx/sites-available/${DOMAIN} /etc/nginx/sites-enabled/${DOMAIN}
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

echo ""
echo "Pour activer HTTPS (après que le DNS A record pointe vers ce VPS):"
echo "  certbot --nginx -d ${DOMAIN} -d www.${DOMAIN} --non-interactive --agree-tos -m admin@${DOMAIN}"
echo ""

# Configurer supervisor pour queue worker
cp /tmp/supervisor-worker.conf /etc/supervisor/conf.d/globalafricaplus-worker.conf 2>/dev/null || \
  echo "-> Copiez deploy/supervisor-worker.conf vers /etc/supervisor/conf.d/"
supervisorctl reread && supervisorctl update || true

# Cron Laravel scheduler
(crontab -u ${APP_USER} -l 2>/dev/null; echo "* * * * * cd ${APP_PATH}/current && php artisan schedule:run >> /dev/null 2>&1") | crontab -u ${APP_USER} -

# Autoriser le user deploy à reload PHP-FPM sans mot de passe
cat > /etc/sudoers.d/deploy-reload <<EOF
${APP_USER} ALL=(root) NOPASSWD: /bin/systemctl reload php${PHP_VERSION}-fpm
${APP_USER} ALL=(root) NOPASSWD: /bin/systemctl restart php${PHP_VERSION}-fpm
${APP_USER} ALL=(root) NOPASSWD: /usr/bin/supervisorctl restart globalafricaplus-worker:*
EOF
chmod 0440 /etc/sudoers.d/deploy-reload

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  ✅ Provisioning terminé"
echo "  Prochaines étapes :"
echo "    1. Ajoutez la clé publique SSH de GitHub Actions dans"
echo "       /home/${APP_USER}/.ssh/authorized_keys"
echo "    2. Éditez ${APP_PATH}/shared/.env (DB, IDNorm, mail…)"
echo "    3. Pointez le DNS A record globalafricaplus.com → IP de ce VPS"
echo "    4. Lancez certbot (voir commande ci-dessus) pour HTTPS"
echo "    5. Déclenchez un push sur main — le CI/CD fera le reste"
echo "════════════════════════════════════════════════════════════════"
