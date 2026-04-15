#!/usr/bin/env bash
#
# down.sh — Baja la última versión de GitHub. Nada más.
#
# Comportamiento:
#   - Descarta cambios locales en ficheros trackeados y untracked no-ignorados
#     para que el pull nunca falle. .env y .claude/ (si estan en .gitignore)
#     no se tocan.
#   - Hace reset --hard a origin/<rama_actual>.
#
# Si necesitas composer install, migrate, clear caches, reload php-fpm,
# hazlo tu a mano. Este script solo sincroniza el código con GitHub.
#
set -e

cd "$(dirname "${BASH_SOURCE[0]}")"

BRANCH="$(git rev-parse --abbrev-ref HEAD)"

echo ">> git fetch origin $BRANCH"
git fetch origin "$BRANCH"

echo ">> git reset --hard origin/$BRANCH"
git reset --hard "origin/$BRANCH"

echo ">> git clean -fd (elimina untracked no-ignorados)"
git clean -fd

echo "OK: sincronizado con origin/$BRANCH @ $(git rev-parse --short HEAD)"
