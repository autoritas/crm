<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferStatus extends Model
{
    protected $fillable = [
        'id_company',
        'status',
        'color',
        'is_default_filter',
    ];

    protected function casts(): array
    {
        return [
            'is_default_filter' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'id_company');
    }
}
