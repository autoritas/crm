<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    protected $connection = 'mysql';

    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
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
        return $this->belongsTo(Company::class, 'company_id');
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
