#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-$(date +%Y%m%d)}"
OUT="${ROOT}/build/smart-school-scheduling-${VERSION}.zip"

mkdir -p "${ROOT}/build"

cd "${ROOT}"

zip -r "${OUT}" . \
  -x "*.git*" \
  -x "*/.env" \
  -x "*/.env.*" \
  -x "*/tests/*" \
  -x "*/docs/audit/*" \
  -x "*/build/*" \
  -x "*/vendor/*" \
  -x "*/writable/logs/*" \
  -x "*/writable/cache/*" \
  -x "*/writable/session/*" \
  -x "*/writable/debugbar/*"

echo "Created ${OUT}"
echo "Run composer install --no-dev on the target server before deploy."
