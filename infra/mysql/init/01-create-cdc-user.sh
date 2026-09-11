#!/bin/sh
# Gate 10 (PLAN.md Task 3.2) — create the dedicated Debezium CDC MySQL account on first init.
#
# Runs once via /docker-entrypoint-initdb.d when the data volume is empty. Grants only what the
# MySQL connector needs for snapshot + binlog reading. The password is injected from the
# DEBEZIUM_DB_PASSWORD environment variable — never hardcoded, and the script exits non-zero if it
# is absent so a misconfigured deployment fails loudly instead of creating a passwordless account.
set -eu

if [ -z "${DEBEZIUM_DB_PASSWORD:-}" ]; then
    echo "01-create-cdc-user: DEBEZIUM_DB_PASSWORD is required but was not set" >&2
    exit 1
fi

DEBEZIUM_USER="${DEBEZIUM_DB_USER:-debezium}"

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE USER IF NOT EXISTS '${DEBEZIUM_USER}'@'%' IDENTIFIED BY '${DEBEZIUM_DB_PASSWORD}';
-- Minimal grants for the Debezium MySQL connector (snapshot + binlog streaming).
GRANT SELECT, RELOAD, SHOW DATABASES, REPLICATION SLAVE, REPLICATION CLIENT, LOCK TABLES ON *.* TO '${DEBEZIUM_USER}'@'%';
FLUSH PRIVILEGES;
SQL

echo "01-create-cdc-user: granted CDC privileges to '${DEBEZIUM_USER}'@'%'"
