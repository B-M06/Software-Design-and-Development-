#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
echo '== PHP syntax checks =='
find app config public tests -name '*.php' -type f -print0 | xargs -0 -n1 php -l
echo '\n== Database-independent checks =='
php tests/static_checks.php
echo '\n== MySQL environment/schema checks =='
php tests/check_environment.php
