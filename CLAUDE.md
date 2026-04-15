# CRM — Guía para Claude

Esta aplicación es un **CRM SaaS multiempresa** desarrollado desde cero para unificar dos soluciones existentes en una única plataforma.

## Objetivo del proyecto

El objetivo de esta aplicación es centralizar en una sola solución:

- La lectura y gestión de **oportunidades** recibidas por email
- La gestión de **ofertas** presentadas
- El registro de **competidores**
- La valoración **cualitativa y cuantitativa** de las ofertas

La aplicación sustituirá progresivamente a las dos soluciones actuales.
Se parte de un **modelo nuevo y limpio**, sin necesidad de mantener compatibilidad con estructuras o nombres antiguos.

## Alcance funcional inicial

La primera versión debe cubrir como mínimo:

- **Oportunidades**
- **Ofertas**
- **Competidores**
- **Valoraciones de competidores y ofertas**

Las dos aplicaciones anteriores son casi idénticas, por lo que la nueva aplicación debe diseñarse como una unificación funcional, no como una simple copia técnica.

---

## Stack técnico

- **Laravel 11+**
- **Filament** para uso interno / panel administrativo
- **Livewire 3**
- **Alpine.js**
- **Tailwind CSS**
- **MySQL**
- Posible uso de **Docker** en desarrollo y eventualmente también en producción
- API pública e integraciones externas desde el inicio

## Finalidad de Filament

Filament se usa como **panel interno y de gestión**.
No se debe asumir que la aplicación está pensada como un panel genérico de administración sin reglas de negocio.
La lógica del dominio debe mantenerse clara y separada.

---

## Naturaleza SaaS y multitenancy

Esta aplicación es **multiempresa** con una única base de datos compartida.

### Regla principal de tenancy

Todos los registros de negocio deben pertenecer a una empresa mediante:

- `company_id`

### Regla obligatoria

Cualquier entidad de negocio debe quedar ligada a una empresa, salvo que exista una razón técnica muy justificada para que sea global.

### Aislamiento de datos

Cada empresa solo puede acceder a sus propios datos.

Claude debe asumir siempre que:

- toda consulta debe estar filtrada por `company_id`
- toda creación debe asignar `company_id`
- toda edición o borrado debe validar la pertenencia a la empresa activa del usuario
- nunca deben mostrarse, agregarse ni relacionarse datos de distintas empresas por error

### Prohibiciones

- No hacer queries sin scope de empresa en modelos de negocio
- No usar selects globales “rápidos” para pruebas que luego queden en producción
- No asumir que un registro es accesible solo por conocer su `id`
- No construir relaciones o métricas cruzando empresas salvo que sea una funcionalidad explícitamente administrativa y segura

---

## Modelo de usuarios, seguridad y autenticación

La gestión de seguridad **no vive en CRM**.

Existe otra aplicación externa, llamada internamente **StockFlow / Core**, que gestiona:

- usuarios
- roles
- aplicaciones
- proyectos
- compañías

Los usuarios se dan de alta allí y CRM hereda esa información.

### Consecuencia para Claude

CRM **no debe convertirse en el sistema maestro de identidad**.

Por defecto, Claude debe asumir que:

- los usuarios ya existen externamente
- la empresa del usuario ya viene definida externamente
- los roles vienen heredados o sincronizados desde el sistema Core
- CRM solo consume o refleja esta información para aplicar permisos y comportamiento

### Regla de diseño

No duplicar innecesariamente lógica de identidad, gestión de usuarios o seguridad que ya pertenezca a Core.

Si CRM necesita datos locales auxiliares del usuario, deben estar claramente separados de la identidad maestra.

---

## Roles y comportamiento por empresa

- Un usuario pertenece a **una sola empresa**
- Los roles son **por empresa**
- La aplicación es la misma para todos los clientes
- El comportamiento cambia según la empresa a la que pertenezca el usuario

Claude debe priorizar un diseño en el que:

- la aplicación sea única
- la personalización sea por configuración
- no se duplique lógica por empresa
- no existan ramas de código paralelas salvo necesidad real

---

## Personalización por empresa

Cada empresa puede personalizar:

- **logo**
- **colores**
- algunos **valores de enum** o configuraciones equivalentes de ciertos campos
- conexión con su propio **tablero Kanboard**

### Gestión de esta personalización

La configuración debe gestionarse desde una opción de **administración**.

### Regla de diseño

La personalización debe resolverse mediante configuración y metadatos, no mediante forks de código.

### Qué evitar

- `if company_id == X` dispersos por toda la aplicación
- vistas duplicadas por empresa
- lógica de negocio distinta metida directamente en componentes Livewire o Blade
- enums rígidos cuando una parte del catálogo de valores debe ser configurable por empresa

### Qué favorecer

- tablas/configuración por empresa
- servicios de branding
- configuración centralizada
- catálogos configurables por empresa para los casos permitidos

---

## Integración con Kanboard

Cada empresa tendrá conexión con **un tablero distinto de Kanboard**.

Claude debe asumir que esta integración:

- es por empresa
- requiere configuración por empresa
- debe resolverse a través de servicios o adaptadores de integración
- no debe acoplar la lógica del CRM directamente al cliente HTTP o a detalles de infraestructura

---

## Módulos de dominio iniciales

## 1. Oportunidades

Representan oportunidades detectadas desde emails u otras fuentes de entrada.

Capacidades esperadas:

- almacenar la oportunidad
- registrar su estado
- enlazar con la empresa propietaria
- permitir evolución hacia oferta
- mantener trazabilidad básica del ciclo

## 2. Ofertas

Representan las ofertas preparadas o presentadas respecto a una oportunidad.

Capacidades esperadas:

- guardar detalle de la oferta
- enlazar con la oportunidad
- almacenar importes, criterios u otros campos relevantes
- registrar la evolución del proceso comercial

## 3. Competidores

Permiten registrar los competidores que concurren en una oportunidad o licitación.

Capacidades esperadas:

- asociar competidores a una oportunidad y/o oferta
- almacenar información identificativa
- almacenar datos comparativos relevantes

## 4. Valoraciones

Permiten registrar valoración cualitativa y cuantitativa de ofertas y competidores.

Capacidades esperadas:

- puntuaciones
- observaciones cualitativas
- criterios configurables si aplica
- trazabilidad por empresa

---

## Origen de datos legacy (sincronización)

Los datos reales de las dos soluciones previas viven en el **mismo host** que se ha dado de alta para Kanboard, distribuidos en **dos bases de datos distintas**:

- **BD de gestión** → datos de **Autoritas**
- **BD de absolute** → datos de **Absolute**

### Tablas relevantes en ambas BDs

- `infonaliadata` → **leads / oportunidades** (presente en las dos BDs)
- `cial_ofertas` → **ofertas** (cabecera)
- `cial_ofertas_has_details` → detalles de oferta (necesario de entrada)
- `cial_ofertas_has_dates` → fechas asociadas a la oferta (necesario de entrada)

### Implicaciones para la sincronización

- Al traer datos hay que **fusionar las dos BDs** asignando correctamente el `company_id` correspondiente (Autoritas vs Absolute) para respetar el aislamiento multiempresa.
- La importación debe ser **idempotente** y encapsulada en servicios / acciones (no scripts sueltos dispersos).
- El esquema legacy (`cial_*`, `infonaliadata`) **no** debe filtrarse al modelo nuevo: se mapea a las entidades limpias del CRM (Oportunidades, Ofertas, etc.).

---

## Filosofía de arquitectura

Claude debe priorizar una arquitectura clara, mantenible y evolutiva.

### Reglas recomendadas

- Modelos Eloquent enfocados a persistencia y relaciones
- Reglas de negocio importantes fuera de Blade y fuera de Resources de Filament
- Servicios / acciones para operaciones de dominio
- Validación mediante Form Requests o clases equivalentes cuando aplique
- Políticas y autorización explícita
- Integraciones externas encapsuladas en servicios
- Configuración multiempresa centralizada

### Evitar

- lógica compleja directamente en Resources de Filament
- reglas de negocio ocultas en componentes Livewire
- uso excesivo de helpers globales
- controladores gigantes
- duplicación de lógica entre panel, API e integraciones

---

## Estructura sugerida del proyecto

Si no existe una decisión más específica, Claude debe tender hacia una estructura parecida a esta:

```text
app/
├── Actions/                 # Casos de uso / acciones de dominio
├── DTOs/                    # Objetos de transporte de datos cuando aporten claridad
├── Enums/                   # Enums del sistema (solo donde tenga sentido)
├── Filament/
│   ├── Resources/
│   └── Pages/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Integrations/
│   ├── Core/
│   └── Kanboard/
├── Models/
├── Policies/
├── Services/
├── Support/
├── Tenancy/
└── Traits/

# Integracion con Stockflow Core (Usuarios y Companias compartidas)

Esta seccion describe como integrar una aplicacion Laravel con la base de datos `core` de Stockflow para compartir usuarios (`stockflow_users`), companias (`companies`), roles y accesos por aplicacion.

## Arquitectura

Cada aplicacion que se integra con Core:
- Tiene su propia base de datos local (tablas especificas del negocio)
- Usa la DB `core` remota (AWS RDS) para: autenticacion, usuarios, companias, accesos y roles
- Hace cifrado de 2FA con una clave maestra `STOCKFLOW_APP_KEY` compartida entre apps (para que el secreto TOTP sea compatible al hacer login en cualquier app del ecosistema)
- Cada app tiene un `APPLICATION_ID` unico en `stockflow_applications`

| Tabla en core | Uso |
|---------------|-----|
| `stockflow_users` | Usuarios compartidos entre todas las apps |
| `stockflow_accesses` | Matriz usuario → aplicacion → rol (acceso a cada app) |
| `stockflow_applications` | Lista de aplicaciones del ecosistema |
| `stockflow_roles` | Roles por aplicacion (admin, editor, viewer, etc.) |
| `companies` | Companias (cliente al que pertenece el usuario) |
| `customers` / `projects` | Clientes/proyectos (si la app los usa) |

## Paso 1: Configuracion de la conexion

### `.env`

```env
# DB local de la aplicacion
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=miapp_production
DB_USERNAME=...
DB_PASSWORD=...

# DB core (compartida)
DB_CORE_CONNECTION=autoritas_production
DB_CORE_HOST=plus.cuiwgjxhyabk.eu-south-2.rds.amazonaws.com
DB_CORE_PORT=3306
DB_CORE_DATABASE=core
DB_CORE_USERNAME=...
DB_CORE_PASSWORD=...

# Clave maestra de cifrado (compartida entre todas las apps del ecosistema)
# Se usa para cifrar/descifrar el secreto de 2FA en stockflow_users.two_factor_secret
STOCKFLOW_APP_KEY=base64:...
```

### `config/database.php`

Agregar la conexion `autoritas_production` al array `connections`:

```php
'autoritas_production' => [
    'driver' => 'mysql',
    'host' => env('DB_CORE_HOST', '127.0.0.1'),
    'port' => env('DB_CORE_PORT', '3306'),
    'database' => env('DB_CORE_DATABASE', 'core'),
    'username' => env('DB_CORE_USERNAME', 'root'),
    'password' => env('DB_CORE_PASSWORD', ''),
    'unix_socket' => env('DB_SOCKET', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
    'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
],
```

### `config/services.php`

Agregar la seccion `stockflow` con la clave maestra:

```php
'stockflow' => [
    'app_key' => env('STOCKFLOW_APP_KEY'),
],
```

## Paso 2: Modelo User apuntando a stockflow_users

**`app/Models/User.php`**:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $connection = 'autoritas_production';
    protected $table = 'stockflow_users';

    protected $fillable = [
        'company_id', 'username', 'email', 'first_name', 'last_name',
        'phone', 'avatar_path', 'password', 'password_changed_at',
        'must_change_password', 'nb_failed_login', 'lock_expiration_date',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
        'must_enable_2fa', 'is_active', 'last_login_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'must_enable_2fa' => 'boolean',
            'is_ldap_user' => 'boolean',
            'is_external' => 'boolean',
        ];
    }

    // ── Accessors ──────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function getInitialsAttribute(): string
    {
        $first = mb_strtoupper(mb_substr($this->first_name ?? '', 0, 1));
        $last = mb_strtoupper(mb_substr($this->last_name ?? '', 0, 1));
        return $first . $last ?: '??';
    }

    // ── Relationships ──────────────────────────────────────────

    public function accesses()
    {
        return $this->hasMany(StockflowAccess::class, 'user_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // ── Role helpers ─────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->accesses()
            ->forApp(StockflowAccess::MY_APP_ID)
            ->where('role_id', 1) // role_id=1 = admin en stockflow_roles
            ->exists();
    }

    // ── Auth helpers ───────────────────────────────────────────

    public function hasAppAccess(int $appId): bool
    {
        return $this->accesses()
            ->forApp($appId)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function has2FA(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * Descifra el secreto de 2FA usando STOCKFLOW_APP_KEY (no APP_KEY).
     * Esto permite que el secreto sea compatible entre todas las apps del ecosistema.
     */
    public function decryptTwoFactorSecret(): ?string
    {
        $raw = $this->getRawOriginal('two_factor_secret');
        if (empty($raw)) return null;

        $key = config('services.stockflow.app_key');
        if (!$key) return $raw;

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        try {
            $encrypter = new Encrypter($key, config('app.cipher'));
            return $encrypter->decrypt($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Lock / failed login ────────────────────────────────────

    public function isLocked(): bool
    {
        return $this->lock_expiration_date && $this->lock_expiration_date > now()->timestamp;
    }

    public function incrementFailedLogin(): void
    {
        $this->increment('nb_failed_login');
        if ($this->nb_failed_login >= 5) {
            $this->update(['lock_expiration_date' => now()->addMinutes(15)->timestamp]);
        }
    }

    public function resetFailedLogin(): void
    {
        $this->update(['nb_failed_login' => 0, 'lock_expiration_date' => null]);
    }
}
```

## Paso 3: Modelo StockflowAccess (matriz usuario-aplicacion)

**`app/Models/StockflowAccess.php`**:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockflowAccess extends Model
{
    use SoftDeletes;

    /**
     * ID de la aplicacion actual en stockflow_applications.
     * Cambia este valor segun el app_id que corresponda.
     *
     * Apps conocidas (ver tabla stockflow_applications):
     *   1 = KB+
     *   2 = CRM
     *   4 = CORE
     *   5 = VISTA
     *   6 = OUTPU (Output Engine)
     *   7 = CLIPP (Clippea)
     *   8 = FLUX (Fluxia Governance)
     *   9 = DIPMA
     */
    const MY_APP_ID = 7; // <-- Cambia por el ID que corresponda a tu app

    protected $connection = 'autoritas_production';
    protected $table = 'stockflow_accesses';

    protected $fillable = ['user_id', 'application_id', 'role_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForApp($query, int $appId)
    {
        return $query->where('application_id', $appId)->where('is_active', true);
    }
}
```

## Paso 4: Modelo Company

Para que el usuario solo vea contenido de su `company_id`:

**`app/Models/Company.php`**:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $connection = 'autoritas_production';
    protected $table = 'companies';

    protected $fillable = ['name', 'abbrev', 'cif', 'email', 'phone', 'is_active'];

    public function users()
    {
        return $this->hasMany(User::class, 'company_id');
    }
}
```

## Paso 5: AuthController (login + 2FA + change-password)

**`app/Http/Controllers/Auth/AuthController.php`**:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StockflowAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) return redirect()->route('dashboard');
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('Invalid credentials.')]);
        }

        if (!$user->is_active) {
            return back()->withErrors(['email' => __('Your account is deactivated.')]);
        }

        if ($user->isLocked()) {
            return back()->withErrors(['email' => __('Account locked. Try again later.')]);
        }

        // Hash::check contra el password almacenado en stockflow_users.password (bcrypt estandar)
        if (!Hash::check($request->password, $user->password)) {
            $user->incrementFailedLogin();
            return back()->withErrors(['email' => __('Invalid credentials.')]);
        }

        // Verifica que el usuario tenga acceso a ESTA aplicacion
        if (!$user->hasAppAccess(StockflowAccess::MY_APP_ID)) {
            return back()->withErrors(['email' => __('You do not have access to this app.')]);
        }

        // 2FA check
        if ($user->has2FA()) {
            $request->session()->put('2fa_user_id', $user->id);
            $request->session()->put('2fa_remember', $request->boolean('remember'));
            return redirect()->route('two-factor');
        }

        return $this->completeLogin($user, $request->boolean('remember'), $request);
    }

    public function showTwoFactor(Request $request)
    {
        if (!$request->session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }
        return view('auth.two-factor');
    }

    public function verifyTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $userId = $request->session()->get('2fa_user_id');
        if (!$userId) return redirect()->route('login');

        $user = User::findOrFail($userId);

        // Descifra el secreto usando STOCKFLOW_APP_KEY (no APP_KEY)
        $secret = $user->decryptTwoFactorSecret();
        if (!$secret) {
            return back()->withErrors(['code' => __('Error verifying code.')]);
        }

        $google2fa = new Google2FA();
        if (!$google2fa->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => __('Incorrect code.')]);
        }

        $remember = $request->session()->get('2fa_remember', false);
        $request->session()->forget(['2fa_user_id', '2fa_remember']);

        return $this->completeLogin($user, $remember, $request);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    private function completeLogin(User $user, bool $remember, Request $request)
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->update(['last_login_at' => now()]);
        $user->resetFailedLogin();

        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->intended(route('dashboard'));
    }
}
```

## Paso 6: TwoFactorController (setup del 2FA)

Genera secreto TOTP y lo cifra con `STOCKFLOW_APP_KEY` para que sea compatible con el resto del ecosistema.

**`app/Http/Controllers/Auth/TwoFactorController.php`**:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function setup()
    {
        $user = auth()->user();

        if (!$user->must_enable_2fa && $user->two_factor_confirmed_at) {
            return redirect()->route('dashboard');
        }

        $google2fa = new Google2FA();

        if (empty($user->getRawOriginal('two_factor_secret'))) {
            $secret = $google2fa->generateSecretKey();
            $user->forceFill([
                'two_factor_secret' => $this->encryptSecret($secret),
            ])->save();
        } else {
            $secret = $user->decryptTwoFactorSecret();
        }

        $qrUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return view('auth.two-factor-setup', compact('qrUrl', 'secret'));
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = auth()->user();
        $secret = $user->decryptTwoFactorSecret();

        $google2fa = new Google2FA();
        if (!$google2fa->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => __('Incorrect code.')]);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'must_enable_2fa' => false,
        ])->save();

        return redirect()->route('dashboard');
    }

    /**
     * Cifra el secreto con STOCKFLOW_APP_KEY (no APP_KEY).
     * Asi el secreto es compatible con cualquier app del ecosistema.
     */
    private function encryptSecret(string $secret): string
    {
        $key = config('services.stockflow.app_key');
        if (!$key) return $secret;

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        $encrypter = new Encrypter($key, config('app.cipher'));
        return $encrypter->encrypt($secret);
    }
}
```

## Paso 7: Middleware EnsureUserAccess

Revoca el login si el usuario pierde el acceso a la app o se desactiva.

**`app/Http/Middleware/EnsureUserAccess.php`**:

```php
<?php

namespace App\Http\Middleware;

use App\Models\StockflowAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) return $next($request);

        $user = Auth::user();

        if (!$user || !$user->is_active || !$user->hasAppAccess(StockflowAccess::MY_APP_ID)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => __('Your access has been revoked.')]);
        }

        return $next($request);
    }
}
```

Registrar en `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        EnsureUserAccess::class,
        // EnsurePasswordChanged::class, // opcional
    ]);
})
```

## Paso 8: Scoping por company_id

Para que cada usuario solo vea contenido de su compania, se aplica un **Global Scope** en los modelos relevantes que tengan `company_id`.

**`app/Models/Scopes/CompanyScope.php`**:

```php
<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check() && auth()->user()->company_id) {
            $builder->where($model->getTable() . '.company_id', auth()->user()->company_id);
        }
    }
}
```

Aplicarlo en los modelos locales que deban filtrar por compania:

```php
use App\Models\Scopes\CompanyScope;

class Project extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        // Auto-asignar company_id al crear
        static::creating(function ($model) {
            if (auth()->check() && empty($model->company_id)) {
                $model->company_id = auth()->user()->company_id;
            }
        });
    }
}
```

**Importante**: Los superadmins (con role admin del app) pueden necesitar saltar el scope. Puedes agregar esto en el `apply()`:

```php
if (auth()->check() && auth()->user()->isAdmin()) {
    return; // Admin ve todo
}
```

## Paso 9: Rutas de auth

**`routes/web.php`** (dentro del grupo `web`):

```php
// Login
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 2FA challenge (durante login)
Route::get('/two-factor', [AuthController::class, 'showTwoFactor'])->name('two-factor');
Route::post('/two-factor', [AuthController::class, 'verifyTwoFactor'])->name('two-factor.verify');

// 2FA setup (activar 2FA)
Route::middleware('auth')->group(function () {
    Route::get('/2fa/setup', [TwoFactorController::class, 'setup'])->name('2fa.setup');
    Route::post('/2fa/setup', [TwoFactorController::class, 'verify'])->name('2fa.verify');
});
```

## Paso 10: Desactivar Laravel Fortify (si existe)

Si la aplicacion venia con Fortify, deshabilita sus features en `config/fortify.php` para evitar colisiones de nombres de ruta (`password.reset`, `two-factor.login`, etc.):

```php
'features' => [
    // Features::registration(),
    // Features::resetPasswords(),
    // Features::twoFactorAuthentication(),
    // Todas comentadas - usamos AuthController custom
],
```

Tambien quitar `FortifyServiceProvider::class` de `bootstrap/app.php` si estaba registrado.

## Cifrado y seguridad

- **Passwords**: se guardan con `Hash::make()` (bcrypt estandar de Laravel). `APP_KEY` de la app local o `STOCKFLOW_APP_KEY` NO importan para passwords, solo el cost de bcrypt.
- **2FA secret (`two_factor_secret`)**: cifrado con `STOCKFLOW_APP_KEY` + AES-256-CBC. Por eso el usuario puede loggear con su mismo autenticador en cualquier app del ecosistema.
- **APP_KEY local**: se usa solo para encriptacion especifica de la app (ej: sessions, cookies, campos locales cifrados).
- **Recovery codes**: si los usas, tambien se cifran con `STOCKFLOW_APP_KEY`.

## Relaciones cross-DB

Importante: como `User` y `Company` estan en otra conexion, las relaciones Eloquent cruzadas desde modelos locales requieren precaucion:

### No funciona (rompe en runtime con hosts diferentes):
```php
// En modelo local MiTabla:
public function user() {
    return $this->belongsTo(User::class); // Laravel intenta JOIN y busca stockflow_users en DB local
}
```

### Si funciona:
- **Cuando el modelo relacionado declara su `$connection`**, las queries se ejecutan separadas (no JOIN). `belongsTo`, `hasMany`, `hasOne` funcionan correctamente siempre que los dos modelos tengan su `$connection` bien puesto.
- Para `belongsToMany` cross-DB con tabla pivot local: usa un modelo explicito de pivot (en la conexion local) en lugar de belongsToMany directo.
- Evita usar `whereHas`/`whereDoesntHave` cruzando conexiones: Laravel intenta generar un JOIN que falla. Alternativa: hacer dos queries separadas.

Ejemplo de `whereDoesntHave` que falla y como evitarlo:

```php
// ❌ Falla: intenta JOIN entre clippea.clips y stockflow_users
Clip::whereDoesntHave('userReadClips', fn($q) => $q->where('user_id', auth()->id()))->get();

// ✅ OK: usa el modelo pivot local (UserReadClip en DB local)
// Asegurate que Clip::userReadClips() sea hasMany(UserReadClip::class), no belongsToMany(User::class)
```

## Paso 11: Control de accesos y roles

El sistema tiene **dos niveles de autorizacion**:

1. **Acceso a la aplicacion**: verificado en login via `stockflow_accesses` (el usuario debe tener un registro activo para `application_id = MY_APP_ID`).
2. **Rol dentro de la aplicacion**: el `role_id` en ese registro determina qué puede hacer el usuario dentro (admin, editor, viewer...).

### Tabla `stockflow_roles`

Cada aplicacion define sus propios roles en `stockflow_roles`. Convencion habitual:

| role_id | Nombre | Permisos tipicos |
|---------|--------|-----------------|
| 1 | admin | Acceso total: gestion de usuarios, configuracion, todas las operaciones |
| 2 | editor | Crear/editar contenido, pero no configuracion ni usuarios |
| 3 | viewer | Solo lectura |

(Los IDs exactos dependen de la configuracion de `stockflow_roles` - consulta la tabla para tu app).

### Metodos helper en User model

Extender `User` con helpers para consultar el rol en la app actual:

```php
// En app/Models/User.php

/**
 * Devuelve el registro de acceso del usuario para la aplicacion actual.
 */
public function currentAppAccess(): ?StockflowAccess
{
    return $this->accesses()
        ->forApp(StockflowAccess::MY_APP_ID)
        ->first();
}

/**
 * Devuelve el role_id del usuario en la aplicacion actual, o null.
 */
public function roleId(): ?int
{
    return $this->currentAppAccess()?->role_id;
}

/**
 * Devuelve true si el usuario tiene uno de los role_ids pasados.
 */
public function hasRole(int ...$roleIds): bool
{
    $current = $this->roleId();
    return $current !== null && in_array($current, $roleIds);
}

/**
 * Helpers semanticos (ajusta los IDs segun tus stockflow_roles).
 */
public function isAdmin(): bool  { return $this->hasRole(1); }
public function isEditor(): bool { return $this->hasRole(1, 2); } // admin o editor
public function isViewer(): bool { return $this->hasRole(1, 2, 3); } // cualquier rol valido
```

### Middleware por rol

**`app/Http/Middleware/RequireRole.php`**:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Uso: ->middleware('role:1,2') para permitir admin o editor.
     */
    public function handle(Request $request, Closure $next, ...$roleIds): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $required = array_map('intval', $roleIds);
        if (!$user->hasRole(...$required)) {
            abort(403, __('You do not have permission to access this resource.'));
        }

        return $next($request);
    }
}
```

Registrar el alias en `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        EnsureUserAccess::class,
    ]);
    $middleware->alias([
        'role' => \App\Http\Middleware\RequireRole::class,
    ]);
})
```

Uso en rutas:

```php
// Solo admins pueden gestionar usuarios
Route::middleware(['auth', 'role:1'])->group(function () {
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::post('/admin/users', [UserController::class, 'store']);
});

// Admins o editores pueden crear contenido
Route::middleware(['auth', 'role:1,2'])->group(function () {
    Route::post('/posts', [PostController::class, 'store']);
    Route::put('/posts/{id}', [PostController::class, 'update']);
});

// Cualquier rol con acceso (incluyendo viewer)
Route::middleware(['auth'])->group(function () {
    Route::get('/posts', [PostController::class, 'index']);
});
```

### Gates y Policies (opcional, mas granular)

Para logica de permisos mas compleja, usa `Gate` en `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('manage-users', fn($user) => $user->isAdmin());
    Gate::define('edit-content', fn($user) => $user->isEditor());
    Gate::define('view-content', fn($user) => $user->isViewer());

    // Logica combinada: solo admin o el autor
    Gate::define('edit-post', function ($user, $post) {
        return $user->isAdmin() || $post->user_id === $user->id;
    });
}
```

Uso:

```php
// En controladores
if (!Gate::allows('manage-users')) abort(403);
Gate::authorize('edit-post', $post);

// En rutas
Route::middleware('can:manage-users')->group(...);

// En vistas Blade
@can('manage-users')
    <a href="/admin/users">Users</a>
@endcan

@cannot('edit-content')
    <p>Solo lectura</p>
@endcannot
```

### Mostrar/ocultar UI segun rol

En las vistas Blade puedes usar los helpers directamente:

```blade
@if(auth()->user()->isAdmin())
    <a href="/admin/settings">Settings</a>
@endif

@if(auth()->user()->isEditor())
    <button>Crear nuevo</button>
@else
    <span class="text-gray-400">Solo lectura</span>
@endif
```

### Scopear queries por rol dentro de la misma company

A veces un `editor` solo debe ver sus propios registros, mientras un `admin` ve todos los de la compania. Se combina con `CompanyScope`:

```php
class Post extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope()); // filtra por company_id

        // Ademas, si es editor/viewer, solo sus propios posts
        static::addGlobalScope('owned', function (Builder $builder) {
            if (auth()->check() && !auth()->user()->isAdmin()) {
                $builder->where('user_id', auth()->id());
            }
        });
    }
}
```

### Gestion de accesos desde la UI (admin)

El admin de la aplicacion suele poder:
- Listar usuarios de su compania (`User::where('company_id', auth()->user()->company_id)`)
- Conceder/revocar acceso: crear o soft-delete en `stockflow_accesses`
- Cambiar rol: actualizar `role_id` en `stockflow_accesses`

Ejemplo de controlador:

```php
public function grantAccess(Request $request, int $userId)
{
    Gate::authorize('manage-users');

    $request->validate([
        'role_id' => 'required|integer|in:1,2,3', // ajusta a tus roles
    ]);

    StockflowAccess::updateOrCreate(
        [
            'user_id' => $userId,
            'application_id' => StockflowAccess::MY_APP_ID,
        ],
        [
            'role_id' => $request->role_id,
            'is_active' => true,
        ]
    );

    return back()->with('success', __('Access granted.'));
}

public function revokeAccess(int $userId)
{
    Gate::authorize('manage-users');

    StockflowAccess::where('user_id', $userId)
        ->forApp(StockflowAccess::MY_APP_ID)
        ->delete(); // soft delete

    return back()->with('success', __('Access revoked.'));
}
```

### Resumen del flujo de autorizacion

```
Request entrante
     │
     ├─ Middleware `auth`
     │   └─ ¿Usuario autenticado? → sí, continua
     │
     ├─ Middleware `EnsureUserAccess` (global)
     │   ├─ ¿is_active = true? → sí
     │   └─ ¿hasAppAccess(MY_APP_ID)? → sí, continua
     │
     ├─ Middleware `role:1,2` (por ruta)
     │   └─ ¿user.role_id IN (1, 2)? → sí, continua
     │
     ├─ Gate/Policy en el controlador (logica fina)
     │   └─ Gate::authorize('edit-post', $post)
     │
     ├─ Global Scopes en los modelos
     │   ├─ CompanyScope → filtra por company_id
     │   └─ OwnedScope → filtra por user_id si no es admin
     │
     └─ Respuesta final
```

## Checklist de integracion

- [ ] `.env` con `DB_CORE_*` y `STOCKFLOW_APP_KEY`
- [ ] `config/database.php` con conexion `autoritas_production`
- [ ] `config/services.php` con `stockflow.app_key`
- [ ] `app/Models/User.php` apuntando a `stockflow_users` via conexion core
- [ ] `app/Models/StockflowAccess.php` con el `APP_ID` correcto
- [ ] `app/Models/Company.php` (opcional, si usas scoping)
- [ ] Metodos `roleId()`, `hasRole()`, `isAdmin()`, `isEditor()` en User
- [ ] `AuthController`, `TwoFactorController`, vistas de auth
- [ ] Middleware `EnsureUserAccess` registrado en `bootstrap/app.php`
- [ ] Middleware alias `role` registrado
- [ ] Gates/Policies definidos en `AppServiceProvider`
- [ ] `CompanyScope` aplicado en modelos que filtran por compania
- [ ] Fortify desactivado (si existia) para evitar colisiones de rutas
- [ ] Registro manual del usuario en `stockflow_accesses` con `application_id = MY_APP_ID` y `role_id` correcto
- [ ] Rutas protegidas con `role:X` segun permisos
- [ ] UI condicional con `@can`/`@cannot` o helpers `isAdmin()`
- [ ] Probar login, 2FA setup, 2FA challenge, y revocacion de acceso
- [ ] Probar acceso denegado (usuario sin `stockflow_accesses` row)
- [ ] Probar cambio de rol (editor ↔ admin) y verificar que la UI cambia
