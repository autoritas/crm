#!/usr/bin/env bash
#
# down.sh — Baja la última versión de GitHub. Nada más.
#
# Comportamiento:
#   - Descarta cambios locales en ficheros trackeados y untracked no-ignorados
#     para que el pull nunca falle. .env y .claude/ (si estan en .gitignore)
#     no se tocan.
#   - Hace reset --hard a origin/<rama_actual>.
#   - Preserva assets especificos de entorno (ver PRESERVE_PATHS abajo) ante
#     cualquier cambio en el repo (por si alguien los trackea por error).
#
# Si necesitas composer install, migrate, clear caches, reload php-fpm,
# hazlo tu a mano. Este script solo sincroniza el código con GitHub.
#
set -e

cd "$(dirname "${BASH_SOURCE[0]}")"

BRANCH="$(git rev-parse --abbrev-ref HEAD)"

# Ficheros especificos del entorno (p.ej. icono/logo de PROD) que nunca
# deben ser sobreescritos por el repo. Se respaldan antes del reset y se
# restauran despues. Basta con anadirlos a .gitignore, pero esto es una red
# de seguridad adicional.
PRESERVE_PATHS=(
    "public/icon.svg"
)

BACKUP_DIR="$(mktemp -d)"
trap 'rm -rf "$BACKUP_DIR"' EXIT

echo ">> Respaldo de ficheros de entorno en $BACKUP_DIR"
for p in "${PRESERVE_PATHS[@]}"; do
    if [ -f "$p" ]; then
        mkdir -p "$BACKUP_DIR/$(dirname "$p")"
        cp -p "$p" "$BACKUP_DIR/$p"
        echo "   - $p respaldado"
    fi
done

echo ">> git fetch origin $BRANCH"
git fetch origin "$BRANCH"

echo ">> git reset --hard origin/$BRANCH"
git reset --hard "origin/$BRANCH"

echo ">> git clean -fd (elimina untracked no-ignorados)"
git clean -fd

echo ">> Restaurando ficheros de entorno"
for p in "${PRESERVE_PATHS[@]}"; do
    if [ -f "$BACKUP_DIR/$p" ]; then
        mkdir -p "$(dirname "$p")"
        cp -p "$BACKUP_DIR/$p" "$p"
        echo "   - $p restaurado"
    fi
done

echo "OK: sincronizado con origin/$BRANCH @ $(git rev-parse --short HEAD)"
