<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competitor extends Model
{
    protected $fillable = ['id_company', 'name', 'cif', 'notes'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'id_company');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(CompetitorAlias::class, 'id_competitor');
    }

    public function offerCompetitors(): HasMany
    {
        return $this->hasMany(OfferCompetitor::class, 'id_competitor');
    }
}
