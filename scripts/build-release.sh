#!/usr/bin/env bash
#
# Thin wrapper — the real build lives in build-release.php so that macOS, Linux
# and Windows all run byte-identical logic. The previous rsync/robocopy pair had
# silently drifted into producing two different packages.
#
# Usage: ./scripts/build-release.sh <version> [--skip-deps] [--no-slim]

set -euo pipefail

exec php "$(dirname "$0")/build-release.php" "$@"
