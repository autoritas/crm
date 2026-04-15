<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorAlias extends Model
{
    use BelongsToCompany;
    protected $fillable = ['company_id', 'raw_name', 'id_competitor'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
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
            ['company_id' => $companyId, 'raw_name' => $normalized]
        );

        return $alias->id_competitor;
    }
}
