<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Matriz usuario -> aplicacion -> rol.
 *
 * Vive en la BD core (`stockflow_accesses`). Determina si un usuario
 * tiene acceso al CRM y con que rol.
 */
class StockflowAccess extends Model
{
    use SoftDeletes;

    /**
     * ID del CRM en `stockflow_applications`.
     * Se configura por entorno con STOCKFLOW_APP_ID:
     *   - desarrollo  (crm.klipea.com)    -> 11
     *   - produccion  (crm.app-util.com)  -> 10
     *
     * @deprecated Usa {@see self::myAppId()} para leer el valor por entorno.
     */
    public const MY_APP_ID = 10;

    public static function myAppId(): int
    {
        return (int) config('services.stockflow.app_id', self::MY_APP_ID);
    }

    protected $connection = 'autoritas_production';
    protected $table = 'stockflow_accesses';

    protected $fillable = ['user_id', 'application_id', 'role_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForApp(Builder $query, int $appId): Builder
    {
        return $query->where('application_id', $appId)->where('is_active', true);
    }
}
