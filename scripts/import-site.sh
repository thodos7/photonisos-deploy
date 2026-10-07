#!/usr/bin/env bash
# Import a WordPress database dump into the running stack and fix the site URL.
#
# Usage:   bash scripts/import-site.sh DUMP.sql OLD_HOST NEW_URL
# Example: bash scripts/import-site.sh /mnt/ai4talos/photonisos/transfer/photonisos.sql \
#              photonisos.local http://ai4talos.aegean.gr:8088
set -euo pipefail

if [ "$#" -ne 3 ]; then
  echo "Usage: $0 DUMP.sql OLD_HOST NEW_URL" >&2
  exit 1
fi
DUMP="$1"
OLD_HOST="$2"
NEW_URL="${3%/}"

cd "$(dirname "$0")/.."

[ -f "$DUMP" ] || { echo "Dump not found: $DUMP" >&2; exit 1; }
[ -f .env ]    || { echo "Missing .env (copy .env.example to .env and fill it in)" >&2; exit 1; }

echo "==> Waiting for WordPress to create wordpress/wp-config.php ..."
for _ in $(seq 1 60); do
  [ -f wordpress/wp-config.php ] && break
  sleep 2
done
[ -f wordpress/wp-config.php ] || { echo "wp-config.php was not created. Check: docker compose logs wordpress" >&2; exit 1; }

echo "==> Importing database ..."
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot "$MYSQL_DATABASE"' < "$DUMP"

echo "==> Replacing URLs (${OLD_HOST} -> ${NEW_URL}) ..."
for scheme in http https; do
  docker compose run --rm -T wpcli wp search-replace "${scheme}://${OLD_HOST}" "${NEW_URL}" \
    --all-tables --skip-columns=guid --report-changed-only
done

echo "==> Flushing caches and rewrite rules ..."
docker compose run --rm -T wpcli wp cache flush
docker compose run --rm -T wpcli wp rewrite flush

echo "==> Sanity check"
echo -n "siteurl: "; docker compose run --rm -T wpcli wp option get siteurl
echo -n "home:    "; docker compose run --rm -T wpcli wp option get home
echo -n "Published photos: "
docker compose run --rm -T wpcli wp post list --post_type=photo --post_status=publish --format=count
docker compose run --rm -T wpcli wp user list --fields=ID,user_login,roles
echo "==> Done. Open ${NEW_URL}/ in a private window."
