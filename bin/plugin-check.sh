#!/usr/bin/env bash
#
# Runs the WordPress.org Plugin Check plugin against the built plugin.
#
# Playground cannot host this: Plugin Check drives PHPCS, which reads php://stdin, and
# PHP.wasm provides no such stream. So this script stands up a disposable WordPress on its
# own MariaDB instance instead. Everything it creates lives in .plugincheck/ and can be
# deleted at any time.
#
# Usage:
#   bin/plugin-check.sh            build, install and run the checks
#   bin/plugin-check.sh --fresh    wipe the site and database first
#   bin/plugin-check.sh --stop     stop the database instance
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$ROOT/.plugincheck"
SITE="$WORK/site"
DATA="$WORK/db"
PORT="${NOPNOE_DB_PORT:-33099}"
SLUG="nopnoe"
DB_NAME="nopnoe_check"
PHP_BIN="${PHP_BIN:-php}"

MARIADBD="${MARIADBD:-$(command -v mariadbd || command -v mysqld || true)}"
MYSQL="${MYSQL:-$(command -v mariadb || command -v mysql || true)}"
INSTALL_DB="${INSTALL_DB:-$(command -v mariadb-install-db || command -v mysql_install_db || true)}"
WP_BIN="${WP_BIN:-$(type -P wp || true)}"

wp() {
	"$PHP_BIN" -d memory_limit=1024M "$WP_BIN" "$@"
}

db_ready() {
	"$MYSQL" --protocol=TCP -h 127.0.0.1 -P "$PORT" -u root -e 'SELECT 1' >/dev/null 2>&1
}

# Our own instance is identified by the pid file in our datadir, not by the port: an
# unrelated server on the same port would otherwise be used, and its database with it.
own_db_running() {
	[ -f "$DATA/mysql.pid" ] && kill -0 "$(cat "$DATA/mysql.pid")" 2>/dev/null
}

stop_db() {
	if [ -f "$DATA/mysql.pid" ]; then
		kill "$(cat "$DATA/mysql.pid")" 2>/dev/null || true
		echo "Stopped the database instance."
	fi
}

if [ "${1:-}" = "--stop" ]; then
	stop_db
	exit 0
fi

for bin in "$MARIADBD" "$MYSQL" "$INSTALL_DB"; do
	[ -n "$bin" ] || {
		echo "MariaDB (mariadbd/mariadb-install-db/mariadb) is required." >&2
		exit 1
	}
done
[ -n "$WP_BIN" ] || {
	echo "WP-CLI (wp) is required." >&2
	exit 1
}

if [ "${1:-}" = "--fresh" ]; then
	stop_db
	rm -rf "$WORK"
fi

mkdir -p "$WORK"

if ! own_db_running; then
	if db_ready; then
		echo "Port $PORT is already used by another database. Set NOPNOE_DB_PORT to a free port." >&2
		exit 1
	fi

	if [ ! -d "$DATA/mysql" ]; then
		echo "Initialising the database in .plugincheck/db ..."
		mkdir -p "$DATA"
		"$INSTALL_DB" --datadir="$DATA" --auth-root-authentication-method=normal --skip-test-db >/dev/null
	fi

	echo "Starting the database on port $PORT ..."
	"$MARIADBD" --datadir="$DATA" --port="$PORT" --socket="$DATA/mysql.sock" \
		--bind-address=127.0.0.1 --pid-file="$DATA/mysql.pid" --log-error="$DATA/err.log" \
		>/dev/null 2>&1 &

	for _ in $(seq 1 30); do
		if db_ready; then
			break
		fi
		sleep 1
	done

	db_ready || {
		echo "The database did not come up; see .plugincheck/db/err.log." >&2
		exit 1
	}
fi

if [ ! -f "$SITE/wp-load.php" ]; then
	echo "Downloading WordPress ..."
	mkdir -p "$SITE"
	wp core download --path="$SITE" --version=latest --force
fi

if [ ! -f "$SITE/wp-config.php" ]; then
	"$MYSQL" --protocol=TCP -h 127.0.0.1 -P "$PORT" -u root -e \
		"CREATE DATABASE IF NOT EXISTS $DB_NAME DEFAULT CHARACTER SET utf8mb4;
		 CREATE USER IF NOT EXISTS '$DB_NAME'@'127.0.0.1' IDENTIFIED BY '$DB_NAME';
		 GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_NAME'@'127.0.0.1';"
	wp config create --path="$SITE" --dbname="$DB_NAME" --dbuser="$DB_NAME" --dbpass="$DB_NAME" \
		--dbhost="127.0.0.1:$PORT" --dbprefix=wp_ --skip-check
	wp core install --path="$SITE" --url=http://127.0.0.1:8080 --title="Plugin Check" \
		--admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
fi

echo "Building the plugin ..."
"$ROOT/bin/build-zip.sh" >/dev/null

# Check exactly what ships, not the repository with its vendor/ and tests/ directories.
rm -rf "${SITE:?}/wp-content/plugins/$SLUG"
ln -sfn "$ROOT/dist/$SLUG" "$SITE/wp-content/plugins/$SLUG"

if ! wp plugin is-installed plugin-check --path="$SITE"; then
	echo "Installing Plugin Check ..."
	wp plugin install plugin-check --activate --path="$SITE"
fi

echo
wp plugin check "$SLUG" --path="$SITE"
