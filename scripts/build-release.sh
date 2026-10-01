#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-$(date +%Y%m%d)}"
OUT="${ROOT}/build/smart-school-scheduling-${VERSION}.zip"

mkdir -p "${ROOT}/build"

cd "${ROOT}"

zip -r "${OUT}" . \
  -x "*.git*" \
  -x ".env" \
  -x "./.env" \
  -x "*/.env" \
  -x "*/.env.*" \
  -x ".agents/*" \
  -x "*/.agents/*" \
  -x ".claude/*" \
  -x "*/.claude/*" \
  -x ".gitnexus/*" \
  -x "*/.gitnexus/*" \
  -x "*/tests/*" \
  -x "*/docs/audit/*" \
  -x "*/build/*" \
  -x "build/*" \
  -x "*/vendor/*" \
  -x "vendor/*" \
  -x "*/writable/logs/*" \
  -x "*/writable/cache/*" \
  -x "*/writable/session/*" \
  -x "*/writable/debugbar/*" \
  -x "writable/installed.lock" \
  -x "*/writable/installed.lock" \
  -x "writable/uploads/*" \
  -x "*/writable/uploads/*" \
  -x "public/uploads/branding/*" \
  -x "*/public/uploads/branding/*"

echo "Created ${OUT}"
echo "Run composer install --no-dev --optimize-autoloader on the target server before deploy."
