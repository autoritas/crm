<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferCompetitorScore extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'id_offer_competitor', 'tecnico', 'economico',
        'objetivo_real', 'objetivo_fake', 'precio',
    ];

    protected function casts(): array
    {
        return [
            'tecnico' => 'decimal:2',
            'economico' => 'decimal:2',
            'objetivo_real' => 'decimal:2',
            'objetivo_fake' => 'decimal:2',
            'precio' => 'decimal:2',
        ];
    }

    public function offerCompetitor(): BelongsTo
    {
        return $this->belongsTo(OfferCompetitor::class, 'id_offer_competitor');
    }
}
