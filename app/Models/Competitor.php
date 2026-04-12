<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_company',
        'id_opportunity',
        'name',
        'contact_info',
        'strengths',
        'weaknesses',
        'notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'id_company');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'id_opportunity');
    }

    public function valuations(): HasMany
    {
        return $this->hasMany(Valuation::class, 'id_competitor');
    }
}
