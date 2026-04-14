# Despliegue en producción

Guía para desplegar / actualizar el CRM en el servidor de producción.

## Requisitos del servidor

- PHP 8.2+ con extensiones: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `gd`
- Composer 2.x
- Node.js 20+ y npm
- MySQL 8.x (acceso a la BD del CRM y a las BDs legacy: `gestion`, `absolute`, `kanboard`)
- Nginx o Apache con `mod_rewrite`
- Usuario del sistema para la app (p. ej. `www-data`)

## Primer despliegue

### 1. Clonar el repo

El repo es **privado**. Se necesita un Personal Access Token de GitHub con permiso `repo`.

```bash
cd /var/www/html
git clone https://<USUARIO>:<TOKEN>@github.com/autoritas/crm.git
cd crm
```

Tras el clonado, **limpia el token** del `.git/config` para no dejarlo en disco:

```bash
git remote set-url origin https://github.com/autoritas/crm.git
```

A partir de aquí cada `git pull` pedirá credenciales (mejor configurar SSH o un credential helper).

### 2. Dependencias

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### 3. Configuración

```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env` con los valores de producción:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://<dominio>`
- `DB_*` (BD principal del CRM)
- `DB_SOURCE_*` (conexiones a BDs legacy `gestion` y `absolute`)
- `DB_KANBOARD_*` (conexión al Kanboard)
- Credenciales de mail, Core, etc.

### 4. Permisos

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 5. Base de datos

```bash
php artisan migrate --force
```

### 6. Caches de producción

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan filament:cache-components
```

### 7. Enlace de storage público

```bash
php artisan storage:link
```

### 8. Servidor web

- Apuntar el document root a `/var/www/html/crm/public`
- Activar HTTPS
- Configurar `supervisor` para colas si se usan (`php artisan queue:work`)
- Configurar `cron` para el scheduler:

  ```
  * * * * * cd /var/www/html/crm && php artisan schedule:run >> /dev/null 2>&1
  ```

## Actualizaciones posteriores

```bash
cd /var/www/html/crm

# Activar modo mantenimiento (opcional)
php artisan down

git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force

# Recompilar caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan filament:cache-components

# Reiniciar workers si hay colas
sudo supervisorctl restart crm-worker:*

php artisan up
```

## Notas de seguridad

- `.env` **no** se versiona. Mantener una copia segura fuera del repo.
- El token usado para clonar el repo debe revocarse una vez configurado el acceso por SSH o credential helper.
- Revisar permisos: solo `www-data` debe escribir en `storage/` y `bootstrap/cache/`.
- Backups periódicos de la BD del CRM antes de cualquier `migrate` en producción.
