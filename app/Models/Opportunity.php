<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_company',
        'title',
        'description',
        'source',
        'status',
        'estimated_value',
        'deadline',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'deadline' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'id_company');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class, 'id_opportunity');
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class, 'id_opportunity');
    }
}
