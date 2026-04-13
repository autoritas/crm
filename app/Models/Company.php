<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'icon_path',
        'primary_color',
        'kanboard_project_id',
        'go_nogo_model',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'id_company');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'id_company');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class, 'id_company');
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class, 'id_company');
    }

    public function kanboardColumns(): HasMany
    {
        return $this->hasMany(CompanyKanboardColumn::class, 'id_company')->orderBy('position');
    }

    public function apiCredentials(): HasMany
    {
        return $this->hasMany(ApiCredential::class, 'id_company');
    }
}
