<?php

namespace App\Models;

use App\Observers\OfferCompetitorObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy(OfferCompetitorObserver::class)]
class OfferCompetitor extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'id_offer', 'competitor_nombre', 'id_competitor',
        'admision', 'razon_exclusion',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'id_offer');
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class, 'id_competitor');
    }

    public function scores(): HasOne
    {
        return $this->hasOne(OfferCompetitorScore::class, 'id_offer_competitor');
    }
}
