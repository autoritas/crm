#!/usr/bin/env bash
#
# push.sh — Sube cambios a GitHub. Nada más.
#
# Uso:
#   ./push.sh                       # commit + push (mensaje por defecto)
#   ./push.sh "mensaje del commit"  # commit + push con mensaje
#
set -e

cd "$(dirname "${BASH_SOURCE[0]}")"

MSG="${1:-chore: update $(date '+%Y-%m-%d %H:%M:%S')}"
BRANCH="$(git rev-parse --abbrev-ref HEAD)"

git add -A

if git diff --cached --quiet; then
    echo ">> Nada que commitear."
else
    git commit -m "$MSG"
fi

git push origin "$BRANCH"
echo "OK: subido a origin/$BRANCH"
