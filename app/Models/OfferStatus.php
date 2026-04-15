<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferStatus extends Model
{
    use BelongsToCompany;
    protected $fillable = [
        'company_id',
        'status',
        'color',
        'is_default_filter',
        'is_default_discard',
    ];

    protected function casts(): array
    {
        return [
            'is_default_filter' => 'boolean',
            'is_default_discard' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
