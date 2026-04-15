<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAlias extends Model
{
    use BelongsToCompany;
    protected $fillable = [
        'company_id',
        'raw_name',
        'id_client',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'id_client');
    }

    /**
     * Registra un nombre crudo y devuelve el id_client normalizado (o null si no está vinculado).
     */
    public static function resolveClientId(int $companyId, string $rawName): ?int
    {
        $normalized = trim($rawName);
        if (empty($normalized)) {
            return null;
        }

        $alias = self::firstOrCreate(
            ['company_id' => $companyId, 'raw_name' => $normalized],
        );

        return $alias->id_client;
    }
}
