<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Empresa maestra en Stockflow Core.
 *
 * Solo identidad: `id`, `name`. El resto de campos que pueda haber en
 * `core.companies` (kanboard_*, go_nogo_model, branding...) son legado
 * de una iteracion anterior y NO se leen ni escriben desde CRM —
 * los ajustes del CRM viven en la tabla local `company_settings` via
 * la relacion `settings()`.
 */
class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'autoritas_production';
    protected $table = 'companies';

    // CRM no actualiza companies en Core. Dejamos $fillable vacio para
    // evitar writes accidentales. Los lectores acceden por attribute.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // -- Identidad / usuarios (Core) --------------------------------------

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id');
    }

    // -- Ajustes locales del CRM (otra conexion, sin FK fisica) -----------
    //
    // Eloquent ejecuta queries separadas por modelo cuando cada uno declara
    // su `$connection`. `company_id` en las tablas locales es una FK logica
    // hacia `core.companies.id`.

    public function settings(): HasOne
    {
        return $this->hasOne(CompanySetting::class, 'company_id');
    }

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
