#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:?Usage: build-release.sh <version>}"
SRC_DIR="$(pwd)"
BUILD_DIR="/tmp/makeai-release-build"
ZIP_NAME="makeai-v${VERSION}.zip"

echo "==> Cleaning up previous build directory"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/public_html/makeai"

echo "==> Installing PHP dependencies (production)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Installing JS dependencies & building frontend"
npm ci
npm run build

echo "==> Copying application source into makeai/"
rsync -a "$SRC_DIR"/ "$BUILD_DIR/public_html/makeai/" \
    --exclude='/.git' \
    --exclude='/node_modules' \
    --exclude='/tests' \
    --exclude='/.env' \
    --exclude='/.env.testing' \
    --exclude='/storage' \
    --exclude='/bootstrap/cache' \
    --exclude='/database/database.sqlite' \
    --exclude='.gitignore' \
    --exclude='.gitattributes' \
    --exclude='.gitkeep' \
    --exclude='/scripts' \
    --exclude='/caveman' \
    --exclude='/.commandcode' \
    --exclude='/.cursor' \
    --exclude='/.vscode' \
    --exclude='/.idea' \
    --exclude='/distribution' \
    --exclude='/dist' \
    --exclude='/public' \
    `# ── Dev/AI/planning artifacts that must NEVER ship to buyers ──` \
    --exclude='/.agent' \
    --exclude='/.agent-mem' \
    --exclude='/.agents' \
    --exclude='/.brainsync' \
    --exclude='/.codex' \
    --exclude='/.claude' \
    --exclude='/.github' \
    --exclude='/.tmp' \
    --exclude='/.mcp.json' \
    --exclude='/.mcp.json.bak' \
    --exclude='/.editorconfig' \
    --exclude='/.npmrc' \
    --exclude='/.phpstorm.meta.php' \
    --exclude='/.phpunit.result.cache' \
    --exclude='/_ide_helper.php' \
    --exclude='/_ide_helper_models.php' \
    --exclude='/phpunit.xml' \
    --exclude='/debug_rag.php' \
    --exclude='/_check_page.php' \
    --exclude='/getMessage()' \
    --exclude='/tsc_errors.txt' \
    --exclude='/tsc_out.txt' \
    `# Root-level docs, prompts, plans, exports, binaries, mockups (anchored to root only)` \
    --exclude='/*.md' \
    --exclude='/*.svg' \
    --exclude='/*.html' \
    --exclude='/*.xlsx' \
    --exclude='/*.exe' \
    --exclude='/*.jpg' \
    --exclude='/*.jpeg' \
    --exclude='/*.png'

echo "==> Placing pre-built Vite assets at root /build/"
cp -r "$SRC_DIR/public/build" "$BUILD_DIR/public_html/build"
if [ -f "$BUILD_DIR/public_html/build/.vite/manifest.json" ]; then
    cp "$BUILD_DIR/public_html/build/.vite/manifest.json" "$BUILD_DIR/public_html/build/manifest.json"
fi

echo "==> Writing fixed index.php, .htaccess, robots.txt, and favicon.ico to root"
cp "$SRC_DIR/distribution/index.php"   "$BUILD_DIR/public_html/index.php"
cp "$SRC_DIR/distribution/.htaccess"   "$BUILD_DIR/public_html/.htaccess"
cp "$SRC_DIR/distribution/robots.txt"  "$BUILD_DIR/public_html/robots.txt"
cp "$SRC_DIR/distribution/favicon.ico" "$BUILD_DIR/public_html/favicon.ico"

echo "==> Creating empty root-level storage/ tree (web-accessible public disk)"
mkdir -p "$BUILD_DIR/public_html/storage"
touch "$BUILD_DIR/public_html/storage/.gitkeep"

echo "==> Ensuring writable directories exist inside makeai/"
mkdir -p "$BUILD_DIR/public_html/makeai/storage/framework/"{cache,sessions,testing,views}
mkdir -p "$BUILD_DIR/public_html/makeai/storage/logs"
mkdir -p "$BUILD_DIR/public_html/makeai/bootstrap/cache"
# Note: chmod might require admin rights on Windows, but is essential for Linux shared hosting
find "$BUILD_DIR/public_html/makeai/storage" -type d -exec chmod 775 {} \; 2>/dev/null || true
find "$BUILD_DIR/public_html/makeai/bootstrap/cache" -type d -exec chmod 775 {} \; 2>/dev/null || true

echo "==> Removing files that must not ship"
rm -f "$BUILD_DIR/public_html/makeai/.env"
rm -f "$BUILD_DIR/public_html/makeai/.env.testing"

echo "==> Sanity checks"
test -f "$BUILD_DIR/public_html/makeai/vendor/autoload.php" || { echo "FATAL: vendor/ missing"; exit 1; }
# Guard: no oversized binaries or internal artifacts leaked into the package
LEAKED="$(find "$BUILD_DIR/public_html/makeai" -maxdepth 1 \( -name '*.exe' -o -name '_ide_helper*.php' -o -name '.phpstorm.meta.php' -o -name '*.md' \) 2>/dev/null)"
if [ -n "$LEAKED" ]; then echo "FATAL: internal artifacts leaked into package:"; echo "$LEAKED"; exit 1; fi
BIG="$(find "$BUILD_DIR/public_html/makeai" -type f -size +50M 2>/dev/null)"
if [ -n "$BIG" ]; then echo "FATAL: file(s) larger than 50MB found — investigate before shipping:"; echo "$BIG"; exit 1; fi
{ test -f "$BUILD_DIR/public_html/build/manifest.json" || test -f "$BUILD_DIR/public_html/build/.vite/manifest.json"; } || { echo "FATAL: build manifest missing"; exit 1; }
test -f "$BUILD_DIR/public_html/makeai/.env.example"        || { echo "FATAL: .env.example missing"; exit 1; }

echo "==> Zipping"
mkdir -p "$SRC_DIR/dist"
php "$SRC_DIR/scripts/zip.php" "$BUILD_DIR/public_html" "$SRC_DIR/dist/${ZIP_NAME}"

echo "==> Done: ./dist/${ZIP_NAME}"