<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Valuation extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_company',
        'id_offer',
        'id_competitor',
        'score',
        'criteria',
        'qualitative_notes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'id_company');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'id_offer');
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class, 'id_competitor');
    }
}
