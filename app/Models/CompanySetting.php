<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ajustes locales del CRM para una empresa.
 *
 * La identidad (id + name) vive en Core (`autoritas_production.companies`).
 * Esta tabla vive en la BD local del CRM (`mysql`), con `company_id` como PK
 * logica hacia core.companies.id — sin FK fisica (hosts distintos).
 */
class CompanySetting extends Model
{
    protected $connection = 'mysql';
    protected $table = 'company_settings';
    protected $primaryKey = 'company_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'company_id',
        'slug',
        'logo_path',
        'icon_path',
        'primary_color',
        'kanboard_project_id',
        'kanboard_default_category_id',
        'kanboard_default_owner_id',
        'go_nogo_model',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function kanboardColumns(): HasMany
    {
        return $this->hasMany(CompanyKanboardColumn::class, 'company_id', 'company_id')
            ->orderBy('position');
    }
}
