#!/usr/bin/env bash
# Per-theme browser run: switch the QA store theme, then checkout (Stripe 4242), rails drag,
# shopper flow — at 1440 en/light and 375 ar/light. Usage: all-themes.sh [slug ...]
# Env: NODE_PATH (playwright), QA_STATE_DIR, QA_PW_FILE, QA_PHOTO, QA_TENANT_DB.
set -u
cd "$(dirname "$0")/../../.."
DB=${QA_TENANT_DB:-nogrgr_tenant_2_qa}
declare -A IDS=([ecommet]=1 [elora-1]=2 [elora-2]=3 [elora-3]=4 [elora-4]=5 [elora-5]=6 [elora-6]=7 [souqify-1]=8 [souqify-2]=9 [souqify-3]=10 [souqify-4]=11 [souqify-5]=12 [souqify-6]=13)
SLUGS=${*:-ecommet elora-1 elora-2 elora-3 elora-4 elora-5 elora-6 souqify-1 souqify-2 souqify-3 souqify-4 souqify-5 souqify-6}
sql() { MYSQL_PWD=root mysql -uroot -N -B "$DB" -e "$1" 2>/dev/null; }
for slug in $SLUGS; do
  id=${IDS[$slug]}
  sql "INSERT INTO store_settings (\`key\`, value, created_at, updated_at) VALUES ('store_theme', '{\"default\": $id, \"countries\": {}}', NOW(), NOW()) ON DUPLICATE KEY UPDATE value = VALUES(value)"
  echo "===== $slug"
  for mode in 1440-en-light 375-ar-light; do
    export QA_TAG="$slug/flow" QA_STATE="$QA_STATE_DIR/state.json"
    echo "--- checkout $mode"
    node tests/e2e/themes/run.mjs tests/e2e/themes/flows/checkout.mjs $mode 2>&1 | grep -E "gateways|usd|after pay url|payment error|STEP FAILED|NOISE|^ \"" | cut -c1-300
    uuid=$(sql "select uuid from orders where customer_id=4 and status='paid' order by id desc limit 1")
    sql "update orders set status='delivered', fulfilment_status='delivered', shipped_at=now(), delivered_at=now() where uuid='$uuid'"
    echo "--- rails $mode"
    node tests/e2e/themes/run.mjs tests/e2e/themes/flows/rails.mjs $mode 2>&1 | grep -E "rails total|STEP FAILED" | cut -c1-300
    echo "--- shopper $mode"
    QA_DELIVERED_ORDER=$uuid node tests/e2e/themes/run.mjs tests/e2e/themes/flows/shopper.mjs $mode 2>&1 | grep -E "^(PASS|FAIL)|STEP FAILED|^ \"" | cut -c1-320
  done
done
