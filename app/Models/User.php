<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuario maestro en Stockflow Core (tabla `stockflow_users` en la BD core).
 *
 * El CRM no gestiona identidad: usa al usuario tal y como viene de Core y
 * aplica aqui solo la logica de autorizacion/tenancy especifica del CRM.
 */
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $connection = 'autoritas_production';
    protected $table = 'stockflow_users';

    protected $fillable = [
        'company_id',
        'username',
        'email',
        'first_name',
        'last_name',
        'phone',
        'avatar_path',
        'password',
        'password_changed_at',
        'must_change_password',
        'nb_failed_login',
        'lock_expiration_date',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'must_enable_2fa',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
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

    // -- Relaciones ------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(StockflowAccess::class, 'user_id');
    }

    // -- Accessors -------------------------------------------------------

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function getNameAttribute(): string
    {
        // Compatibilidad con codigo/Filament que espera `name`.
        return $this->full_name ?: (string) $this->email;
    }

    public function getInitialsAttribute(): string
    {
        $first = mb_strtoupper(mb_substr($this->first_name ?? '', 0, 1));
        $last = mb_strtoupper(mb_substr($this->last_name ?? '', 0, 1));
        return $first . $last ?: '??';
    }

    // -- Autorizacion ---------------------------------------------------

    public function hasAppAccess(?int $appId = null): bool
    {
        return $this->accesses()
            ->forApp($appId ?? StockflowAccess::myAppId())
            ->whereNull('deleted_at')
            ->exists();
    }

    public function currentAppAccess(): ?StockflowAccess
    {
        return $this->accesses()
            ->forApp(StockflowAccess::myAppId())
            ->first();
    }

    public function roleId(): ?int
    {
        return $this->currentAppAccess()?->role_id;
    }

    public function hasRole(int ...$roleIds): bool
    {
        $current = $this->roleId();
        return $current !== null && in_array($current, $roleIds, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(1);
    }

    public function isEditor(): bool
    {
        return $this->hasRole(1, 2);
    }

    public function isViewer(): bool
    {
        return $this->hasRole(1, 2, 3);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasAppAccess();
    }

    // -- 2FA -------------------------------------------------------------

    public function has2FA(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * Descifra el secreto TOTP con STOCKFLOW_APP_KEY para que sea
     * compatible con el resto de apps del ecosistema.
     */
    public function decryptTwoFactorSecret(): ?string
    {
        $raw = $this->getRawOriginal('two_factor_secret');
        if (empty($raw)) {
            return null;
        }

        $key = config('services.stockflow.app_key');
        if (!$key) {
            \Log::warning('STOCKFLOW_APP_KEY no esta configurada; no se puede descifrar 2FA secret.');
            return null;
        }

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        // El ecosistema Stockflow usa AES-256-CBC (ver CLAUDE.md).
        // No dependemos de config('app.cipher') porque este CRM podria
        // estar configurado con GCM y no cuadrar con las otras apps.
        foreach (['AES-256-CBC', 'AES-256-GCM'] as $cipher) {
            try {
                $encrypter = new Encrypter($key, $cipher);
                return $encrypter->decrypt($raw);
            } catch (\Throwable $e) {
                // prueba siguiente cipher
            }
        }

        \Log::warning('No se pudo descifrar 2FA secret. Comprueba STOCKFLOW_APP_KEY.');
        return null;
    }

    // -- Lock / failed login ---------------------------------------------

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
