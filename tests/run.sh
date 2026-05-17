#!/usr/bin/env bash
#
# Test runner for the Moneybag WHMCS module.
#
#   ./tests/run.sh
#
# Always runs the offline structural tests. The live sandbox e2e test runs
# only when MONEYBAG_API_KEY is set in the environment, e.g.:
#
#   MONEYBAG_API_KEY=sk_sandbox_xxx ./tests/run.sh
#
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "### Offline structural tests"
php "${DIR}/test_offline.php"

echo
echo "### End-to-end sandbox test"
php "${DIR}/test_e2e.php"
