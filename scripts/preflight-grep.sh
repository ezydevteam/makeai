#!/usr/bin/env bash
#
# preflight-grep.sh — Envato distribution safety scan for the MakeAI package.
#
# Runs the grep families from dist-checklist.md against a STAGED or EXTRACTED
# package (never the working repo — the repo legitimately holds .env, planning
# docs, dev tooling). Point it at whatever you have:
#
#   scripts/preflight-grep.sh <path>
#
# <path> may be any of:
#   - the zip root            (…/makeai-v1.0.0/)        → resolves script/core
#   - the webroot             (…/script/)               → resolves core
#   - the app dir directly    (…/core/)                 → used as-is
#   - the staging temp tree   (leftover from --keep-build)
#
# With no argument it looks for the staging tree that build-release.php leaves
# behind under the system temp dir when built with --keep-build.
#
# Exit status:
#   0  clean — nothing that would block an Envato submission
#   1  one or more HARD findings (live secrets / placeholder key / debug leftovers)
#   2  usage / could not locate the package's core/ directory
#
# HARD findings fail the build. SOFT findings are printed as warnings (review
# each — some are legitimate, e.g. a documented docs-site constant), and do NOT
# change the exit code on their own.

set -u

# ── locate the app (core/) directory ─────────────────────────────────────────

find_core() {
  local base="$1"
  if   [ -d "$base/script/core" ]; then echo "$base/script/core"
  elif [ -d "$base/core" ];        then echo "$base/core"
  elif [ -f "$base/artisan" ] && [ -d "$base/app" ]; then echo "$base"   # already core/
  else return 1
  fi
}

TARGET="${1:-}"

if [ -z "$TARGET" ]; then
  # Try the staging tree build-release.php leaves with --keep-build.
  STAGE="${TMPDIR:-/tmp}/makeai-release-build"
  [ -d "$STAGE" ] || STAGE="$(dirname "$(mktemp -u)")/makeai-release-build"
  if [ -d "$STAGE" ]; then
    # zip root is makeai-v*/ under the staging root
    TARGET="$(find "$STAGE" -maxdepth 1 -type d -name 'makeai-v*' 2>/dev/null | head -n1)"
    [ -n "$TARGET" ] || TARGET="$STAGE"
  fi
fi

if [ -z "$TARGET" ]; then
  echo "usage: $0 <path-to-staged-or-extracted-package>" >&2
  echo "  (or build with --keep-build first, then run with no argument)" >&2
  exit 2
fi

CORE="$(find_core "$TARGET")" || {
  echo "error: could not find a core/ app directory under: $TARGET" >&2
  echo "  expected <root>/script/core, <root>/core, or the core dir itself." >&2
  exit 2
}

echo "Scanning package: $CORE"
echo

# ── helpers ──────────────────────────────────────────────────────────────────

hard_fail=0
soft_warn=0

# Excluded from every scan: third-party / VCS / build noise. vendor/ is skipped
# at the grep level (not walked then filtered) so the scan stays fast even when
# pointed at a fat tree. node_modules should never be in a real package, but
# excluding it keeps an accidental smoke-test against the repo usable.
EXCL=(--exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git \
      --exclude-dir=storage --exclude-dir=build \
      --exclude=composer.lock --exclude=composer.json)

# scan_hard <label> <regex> [extra grep args...]
# Excludes vendor/ (third-party false positives). Any hit is a HARD failure.
scan_hard() {
  local label="$1"; shift
  local regex="$1"; shift
  local hits
  hits="$(grep -rInE "${EXCL[@]}" "$regex" "$CORE" "$@" 2>/dev/null)"
  if [ -n "$hits" ]; then
    echo "  ✗ HARD  — $label"
    echo "$hits" | sed 's/^/       /' | head -n 40
    [ "$(echo "$hits" | wc -l)" -gt 40 ] && echo "       … (truncated)"
    echo
    hard_fail=1
  else
    echo "  ✓       — $label"
  fi
}

# scan_soft <label> <regex> [paths relative to CORE...]
# Prints hits as warnings. Does NOT fail the build (review manually).
scan_soft() {
  local label="$1"; shift
  local regex="$1"; shift
  local paths=()
  local p
  for p in "$@"; do [ -e "$CORE/$p" ] && paths+=("$CORE/$p"); done
  [ ${#paths[@]} -eq 0 ] && paths=("$CORE")
  local hits
  hits="$(grep -rInE "${EXCL[@]}" "$regex" "${paths[@]}" 2>/dev/null)"
  if [ -n "$hits" ]; then
    echo "  ! SOFT  — $label  (review each; some may be legitimate)"
    echo "$hits" | sed 's/^/       /' | head -n 40
    [ "$(echo "$hits" | wc -l)" -gt 40 ] && echo "       … (truncated)"
    echo
    soft_warn=1
  else
    echo "  ✓       — $label"
  fi
}

# ── 1. HARD: live secrets & private keys ─────────────────────────────────────

echo "── Secrets & private keys (HARD) ─────────────────────────────"
scan_hard "Stripe live keys"          'sk_live|pk_live|rk_live'
scan_hard "AWS access key id"         'AKIA[0-9A-Z]{16}'
scan_hard "private key blocks"        'BEGIN (RSA |EC |OPENSSH |)PRIVATE KEY'
scan_hard "inline password literals"  "password\s*[:=]\s*[\"'][^\"' ]{6,}[\"']"
echo

# ── 2. HARD: placeholder license key (catastrophic silent failure) ───────────

echo "── License public key (HARD) ─────────────────────────────────"
# The file ALWAYS defines both PUBLIC_KEY and a PLACEHOLDER sentinel constant, so
# a naive grep for the placeholder string always matches. Mirror the build's
# preflight: extract both constant VALUES and fail only when they are equal (or
# PUBLIC_KEY is empty). Anything else means a real key is configured.
LK="$CORE/app/Support/LicenseKey.php"
if [ -f "$LK" ]; then
  extract_const() { grep -E "const $1\b" "$LK" | sed -E "s/.*=[[:space:]]*'([^']*)'.*/\1/" | head -n1; }
  pub_key="$(extract_const PUBLIC_KEY)"
  placeholder="$(extract_const PLACEHOLDER)"
  if [ -z "$pub_key" ]; then
    echo "  ✗ HARD  — could not read PUBLIC_KEY from LicenseKey.php"
    hard_fail=1
  elif [ "$pub_key" = "$placeholder" ]; then
    echo "  ✗ HARD  — PUBLIC_KEY is still the placeholder sentinel — NO BUYER CAN ACTIVATE"
    hard_fail=1
  else
    echo "  ✓       — real license public key configured (PUBLIC_KEY != PLACEHOLDER)"
  fi
else
  echo "  ✗ HARD  — app/Support/LicenseKey.php missing from package"
  hard_fail=1
fi
echo

# ── 3. HARD: debug leftovers in shipped source ───────────────────────────────

echo "── Debug leftovers (HARD) ────────────────────────────────────"
DBG='\b(dd|dump|var_dump|ray)\s*\('
dbg_hits="$(grep -rInE "${EXCL[@]}" "$DBG" "$CORE/app" "$CORE/routes" 2>/dev/null; \
            grep -rInE "${EXCL[@]}" 'console\.log' "$CORE/resources" 2>/dev/null)"
if [ -n "$dbg_hits" ]; then
  echo "  ✗ HARD  — debug statement(s) in shipped code"
  echo "$dbg_hits" | sed 's/^/       /' | head -n 40
  hard_fail=1
else
  echo "  ✓       — no dd()/dump()/var_dump()/ray()/console.log"
fi
echo

# ── 4. SOFT: hardcoded hosts, paths, personal identifiers ────────────────────

echo "── Hardcoded values (SOFT — review each) ─────────────────────"
scan_soft "localhost / loopback / dev ports" 'localhost|127\.0\.0\.1|:5173|:8000|ngrok'
scan_soft "absolute dev filesystem paths"    'D:\\\\laragon|/home/[a-z]|/Users/|/var/www/'
scan_soft "personal / internal identifiers"  '@gmail\.com|ezydev'   # DOCS_SITE constant is expected
echo

# ── 5. SOFT: config that must be production-safe ─────────────────────────────

echo "── Production config (SOFT — check .env.example) ─────────────"
if [ -f "$CORE/.env.example" ]; then
  grep -qE '^APP_DEBUG\s*=\s*true'      "$CORE/.env.example" && { echo "  ! SOFT  — APP_DEBUG=true in .env.example"; soft_warn=1; } || echo "  ✓       — APP_DEBUG not true"
  grep -qE '^APP_ENV\s*=\s*(local|dev)' "$CORE/.env.example" && { echo "  ! SOFT  — APP_ENV is local/dev in .env.example"; soft_warn=1; } || echo "  ✓       — APP_ENV not local/dev"
  grep -qE '^APP_KEY\s*=\s*base64:.+'   "$CORE/.env.example" && { echo "  ! SOFT  — APP_KEY has a real value in .env.example (should be blank)"; soft_warn=1; } || echo "  ✓       — APP_KEY blank in .env.example"
else
  echo "  ! SOFT  — .env.example not found in package"
  soft_warn=1
fi
echo

# ── 6. SOFT: leaked .env / dev tooling / planning docs ───────────────────────

echo "── Leaked files (SOFT) ───────────────────────────────────────"
leaked=""
[ -f "$CORE/.env" ] && leaked="$leaked\n  core/.env (LIVE CREDENTIALS)"
for d in .git .cursor .agent .agent-mem .claude .codex .vscode node_modules; do
  [ -e "$CORE/$d" ] && leaked="$leaked\n  core/$d"
done
for f in plan.md ADDONS_PLAN.md AGENT.md AGENTS.md CLAUDE.md tsc_errors.txt tsc_out.txt; do
  [ -f "$CORE/$f" ] && leaked="$leaked\n  core/$f"
done
if [ -n "$leaked" ]; then
  echo "  ! SOFT  — internal file(s) leaked into package:"
  printf "$leaked\n" | sed 's/^/     /'
  soft_warn=1
else
  echo "  ✓       — no obvious internal/dev files leaked"
fi
echo

# ── summary ──────────────────────────────────────────────────────────────────

echo "──────────────────────────────────────────────────────────────"
if [ "$hard_fail" -ne 0 ]; then
  echo "RESULT: ✗ HARD findings present — DO NOT SUBMIT until fixed."
  exit 1
elif [ "$soft_warn" -ne 0 ]; then
  echo "RESULT: ⚠ no hard blockers, but SOFT warnings above need manual review."
  exit 0
else
  echo "RESULT: ✓ clean — no automated blockers found."
  echo "        (Still do the manual ✋ items in dist-checklist.md.)"
  exit 0
fi
