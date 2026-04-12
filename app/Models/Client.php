<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_company',
        'name',
        'cif',
        'sector',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address',
        'province',
        'notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'id_company');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ClientAlias::class, 'id_client');
    }

    public function infonaliaData(): HasMany
    {
        return $this->hasMany(InfonaliaData::class, 'id_client');
    }
}
