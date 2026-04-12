<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAlias extends Model
{
    protected $fillable = [
        'id_company',
        'raw_name',
        'id_client',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'id_company');
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
            ['id_company' => $companyId, 'raw_name' => $normalized],
        );

        return $alias->id_client;
    }
}
