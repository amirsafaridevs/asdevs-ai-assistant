#!/usr/bin/env bash
#
# Build a release package for the WordPress plugin repository.
#
# This script never modifies the source directory: everything is assembled in
# build/ and the finished plugin folder plus zip are written to final/.
# Running it twice produces the same output.
#
set -euo pipefail

SLUG="asdevs-ai-assistant"
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="${SOURCE_DIR}/build"
FINAL_DIR="${SOURCE_DIR}/final"
STAGE_DIR="${BUILD_DIR}/${SLUG}"

say() { printf '\033[0;36m==>\033[0m %s\n' "$1"; }
fail() { printf '\033[0;31mError:\033[0m %s\n' "$1" >&2; exit 1; }

# --- One source of truth for the version ------------------------------------

HEADER_VERSION="$(sed -n 's/^ \* Version:[[:space:]]*\(.*\)$/\1/p' "${SOURCE_DIR}/${SLUG}.php" | head -1 | tr -d '[:space:]')"
CONST_VERSION="$(sed -n "s/^const VERSION = '\(.*\)';$/\1/p" "${SOURCE_DIR}/${SLUG}.php" | head -1)"
README_VERSION="$(sed -n 's/^Stable tag:[[:space:]]*\(.*\)$/\1/p' "${SOURCE_DIR}/readme.txt" | head -1 | tr -d '[:space:]')"

[ -n "${HEADER_VERSION}" ] || fail "No Version header found in ${SLUG}.php."
[ "${HEADER_VERSION}" = "${CONST_VERSION}" ] || fail "Plugin header (${HEADER_VERSION}) and VERSION constant (${CONST_VERSION}) disagree."
[ "${HEADER_VERSION}" = "${README_VERSION}" ] || fail "Plugin header (${HEADER_VERSION}) and readme.txt stable tag (${README_VERSION}) disagree."

VERSION="${HEADER_VERSION}"
say "Building ${SLUG} ${VERSION}"

# --- Build the assets from source, in place, without polluting the package ---

say "Building front-end assets"
( cd "${SOURCE_DIR}/frontend" && npm ci --silent && npm run build --silent )

say "Installing production dependencies"
( cd "${SOURCE_DIR}" && composer install --no-dev --optimize-autoloader --classmap-authoritative --quiet )

# --- Stage only what the plugin needs at runtime ----------------------------

say "Staging package"
rm -rf "${BUILD_DIR}"
mkdir -p "${STAGE_DIR}"

copy() {
  [ -e "${SOURCE_DIR}/$1" ] || return 0
  mkdir -p "${STAGE_DIR}/$(dirname "$1")"
  cp -R "${SOURCE_DIR}/$1" "${STAGE_DIR}/$1"
}

copy "${SLUG}.php"
copy "uninstall.php"
copy "readme.txt"
copy "LICENSE"
copy "src"
copy "vendor"
copy "assets/dist"
copy "languages"

# Nothing that is only needed to develop or build the plugin ships.
find "${STAGE_DIR}" \
  \( -name 'node_modules' -o -name '.git*' -o -name 'tests' -o -name 'test' \
     -o -name 'docs' -o -name 'examples' -o -name '.idea' -o -name '.vscode' \) \
  -prune -exec rm -rf {} + 2>/dev/null || true

find "${STAGE_DIR}" -type f \( \
  -name '*.map' -o -name '*.ts' -o -name '*.vue' -o -name '*.scss' \
  -o -name '*.dist' -o -name '*.lock' -o -name '*.log' -o -name '*.zip' \
  -o -name '*.mp4' -o -name '*.MP4' -o -name '*.mov' \
  -o -name '.DS_Store' -o -name 'Thumbs.db' -o -name '.env*' \
  -o -name 'phpunit.xml*' -o -name 'phpcs.xml*' -o -name 'composer.json' \
  -o -name 'composer.lock' -o -name 'package.json' -o -name 'package-lock.json' \
  -o -name '*.yml' -o -name '*.yaml' -o -name 'Makefile' \) \
  -delete

# Translations: template, compiled catalogues, and the JSON the widget reads.
say "Building translations"
if command -v wp >/dev/null 2>&1; then
  wp i18n make-pot "${SOURCE_DIR}" "${STAGE_DIR}/languages/${SLUG}.pot" \
    --slug="${SLUG}" --domain="${SLUG}" --ignore-domain \
    --exclude="frontend/node_modules,assets/dist,vendor,build,final,plans,tests" \
    --quiet
fi

for po in "${SOURCE_DIR}"/languages/*.po; do
  [ -e "${po}" ] || continue
  name="$(basename "${po}" .po)"
  if command -v msgfmt >/dev/null 2>&1; then
    msgfmt -o "${STAGE_DIR}/languages/${name}.mo" "${po}"
  elif command -v wp >/dev/null 2>&1; then
    wp i18n make-mo "${po}" "${STAGE_DIR}/languages/${name}.mo" --quiet
  fi
done

if command -v wp >/dev/null 2>&1 && [ -f "${SOURCE_DIR}/languages/i18n-map.json" ]; then
  wp i18n make-json "${STAGE_DIR}/languages" \
    --use-map="${SOURCE_DIR}/languages/i18n-map.json" --no-purge --quiet
fi

# --- Emit the finished package ----------------------------------------------

say "Writing package"
rm -rf "${FINAL_DIR}"
mkdir -p "${FINAL_DIR}"
cp -R "${STAGE_DIR}" "${FINAL_DIR}/${SLUG}"

( cd "${FINAL_DIR}" && zip -q -r -X "${SLUG}-${VERSION}.zip" "${SLUG}" )

rm -rf "${BUILD_DIR}"

say "Done: final/${SLUG}-${VERSION}.zip ($(du -h "${FINAL_DIR}/${SLUG}-${VERSION}.zip" | cut -f1))"
