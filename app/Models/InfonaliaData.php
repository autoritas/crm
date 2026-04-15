<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Observers\InfonaliaDataObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(InfonaliaDataObserver::class)]
class InfonaliaData extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'infonalia_data';

    protected $fillable = [
        'company_id',
        'id_decision',
        'id_client',
        'fecha_publicacion',
        'cliente',
        'resumen_objeto',
        'provincia',
        'presupuesto',
        'presentacion',
        'perfil_contratante',
        'fecha_ingreso',
        'url',
        'kanboard_task_id',
        'id_ia_decision',
        'ia_motivo',
        'ia_fecha',
        'revisado_humano',
        'revisado_fecha',
        'id_screening_reason',
        'screening_comment',
    ];

    protected function casts(): array
    {
        return [
            'fecha_publicacion' => 'date',
            'presentacion' => 'date',
            'fecha_ingreso' => 'datetime',
            'presupuesto' => 'decimal:2',
            'ia_fecha' => 'datetime',
            'revisado_humano' => 'boolean',
            'revisado_fecha' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(InfonaliaStatus::class, 'id_decision');
    }

    public function iaDecision(): BelongsTo
    {
        return $this->belongsTo(InfonaliaStatus::class, 'id_ia_decision');
    }

    public function offer(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Offer::class, 'id_infonalia_data');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'id_client');
    }

    public function screeningReason(): BelongsTo
    {
        return $this->belongsTo(ScreeningReason::class, 'id_screening_reason');
    }
}
