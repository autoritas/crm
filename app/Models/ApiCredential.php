<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiCredential extends Model
{
    protected $connection = 'mysql';

    use BelongsToCompany;
    protected $fillable = [
        'company_id', 'service', 'label', 'base_url', 'api_key', 'folder', 'extra', 'is_active',
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
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Obtener credencial activa por servicio (global o por empresa).
     */
    public static function getForService(string $service, ?int $companyId = null): ?self
    {
        return static::where('service', $service)
            ->where('is_active', true)
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id');
                if ($companyId) {
                    $q->orWhere('company_id', $companyId);
                }
            })
            ->orderByRaw('company_id IS NULL ASC')
            ->first();
    }
}
