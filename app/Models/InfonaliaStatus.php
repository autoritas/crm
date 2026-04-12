<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfonaliaStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_company',
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
        return $this->belongsTo(Company::class, 'id_company');
    }
}
