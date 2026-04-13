<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiCredential extends Model
{
    protected $fillable = [
        'id_company', 'service', 'label', 'base_url', 'api_key', 'folder', 'extra', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'extra' => 'array',
            'is_active' => 'boolean',
            'api_key' => 'encrypted',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'id_company');
    }

    /**
     * Obtener credencial activa por servicio (global o por empresa).
     */
    public static function getForService(string $service, ?int $companyId = null): ?self
    {
        return static::where('service', $service)
            ->where('is_active', true)
            ->where(function ($q) use ($companyId) {
                $q->whereNull('id_company');
                if ($companyId) {
                    $q->orWhere('id_company', $companyId);
                }
            })
            ->orderByRaw('id_company IS NULL ASC')
            ->first();
    }
}
