<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorAlias extends Model
{
    protected $fillable = ['id_company', 'raw_name', 'id_competitor'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'id_company');
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class, 'id_competitor');
    }

    public static function resolveCompetitorId(int $companyId, string $rawName): ?int
    {
        $normalized = trim($rawName);
        if (empty($normalized)) return null;

        $alias = self::firstOrCreate(
            ['id_company' => $companyId, 'raw_name' => $normalized]
        );

        return $alias->id_competitor;
    }
}
