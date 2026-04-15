<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfonaliaStatus extends Model
{
    protected $connection = 'mysql';

    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'status',
        'color',
        'generates_offer',
        'is_default_filter',
    ];

    protected function casts(): array
    {
        return [
            'generates_offer' => 'boolean',
            'is_default_filter' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
