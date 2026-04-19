#!/usr/bin/env bash
#
# refresh.sh - wrapper para refrescar la cookie PLACSP con Playwright.
# Ejecutable desde cron. Logs a stdout/stderr (cron redirige a fichero).
#
# Uso:
#   ./refresh.sh
#
# Cron recomendado:
#   */45 * * * * ubuntu /var/www/html/crm/tools/placsp-login/refresh.sh \
#       >> /var/www/html/crm/storage/logs/placsp-login.log 2>&1
#
set -e

cd "$(dirname "${BASH_SOURCE[0]}")"

# Para que cron encuentre node y npx
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:$PATH"

# Asegura que Playwright usa el Chromium local (instalado por npx playwright install)
export PLAYWRIGHT_BROWSERS_PATH="${PLAYWRIGHT_BROWSERS_PATH:-$HOME/.cache/ms-playwright}"

exec node refresh-cookie.js
