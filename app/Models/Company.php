<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Compania maestra en Stockflow Core.
 *
 * Los campos base (name, abbrev, cif, email, phone, is_active) los define
 * core. Los campos especificos del CRM (slug, logos, colores, Kanboard,
 * go_nogo_model) se anaden via migracion adicional sobre la misma tabla core.
 */
class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'autoritas_production';
    protected $table = 'companies';

    protected $fillable = [
        // campos base de core
        'name',
        'abbrev',
        'cif',
        'email',
        'phone',
        'is_active',
        // campos CRM (ver migracion add_crm_fields_to_core_companies)
        'slug',
        'logo_path',
        'icon_path',
        'primary_color',
        'kanboard_project_id',
        'kanboard_default_category_id',
        'kanboard_default_owner_id',
        'go_nogo_model',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id');
    }

    // -- Relaciones locales (otra conexion) ------------------------------
    //
    // Al estar Company en la conexion core y estos modelos en la local,
    // Eloquent ejecuta dos queries separadas (no JOIN) y las relaciones
    // funcionan siempre que cada modelo declare su `$connection`.

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'company_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class, 'company_id');
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class, 'company_id');
    }

    public function kanboardColumns(): HasMany
    {
        return $this->hasMany(CompanyKanboardColumn::class, 'company_id')->orderBy('position');
    }

    public function apiCredentials(): HasMany
    {
        return $this->hasMany(ApiCredential::class, 'company_id');
    }
}
