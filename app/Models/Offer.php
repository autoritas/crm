<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    protected $connection = 'mysql';

    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id', 'id_infonalia_data', 'cliente', 'id_client',
        // Proyecto
        'codigo_proyecto', 'proyecto', 'objeto', 'sector',
        'id_offer_type', 'id_offer_status', 'temperatura',
        // Importes
        'fecha_presentacion', 'importe_licitacion', 'importe_estimado', 'duracion_meses',
        // Detalles
        'id_business_line', 'id_client_activity', 'id_workflow',
        'renovable', 'fidelizacion', 'kanboard_task', 'responsable',
        // Fechas
        'fecha_anuncio', 'fecha_publicacion', 'fecha_adjudicacion',
        'fecha_formalizacion', 'fecha_fin_contrato',
        // Criterios
        'peso_economica', 'peso_tecnica', 'peso_objetiva_real', 'peso_objetiva_fake', 'id_formula',
        // Extra
        'provincia', 'url', 'notas',
        // Go/NoGo
        'go_nogo', 'ia_go_nogo', 'ia_go_nogo_analysis', 'ia_go_nogo_date',
        // IA
        'offer_resources',
    ];

    protected function casts(): array
    {
        return [
            'fecha_presentacion' => 'date:Y-m-d',
            'fecha_anuncio' => 'date:Y-m-d',
            'fecha_publicacion' => 'date:Y-m-d',
            'fecha_adjudicacion' => 'date:Y-m-d',
            'fecha_formalizacion' => 'date:Y-m-d',
            'fecha_fin_contrato' => 'date:Y-m-d',
            'importe_licitacion' => 'decimal:2',
            'importe_estimado' => 'decimal:2',
            'peso_economica' => 'decimal:2',
            'peso_tecnica' => 'decimal:2',
            'peso_objetiva_real' => 'decimal:2',
            'peso_objetiva_fake' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class, 'company_id'); }
    public function infonaliaData(): BelongsTo { return $this->belongsTo(InfonaliaData::class, 'id_infonalia_data'); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class, 'id_client'); }
    public function offerStatus(): BelongsTo { return $this->belongsTo(OfferStatus::class, 'id_offer_status'); }
    public function offerType(): BelongsTo { return $this->belongsTo(OfferType::class, 'id_offer_type'); }
    public function businessLine(): BelongsTo { return $this->belongsTo(OfferBusinessLine::class, 'id_business_line'); }
    public function clientActivity(): BelongsTo { return $this->belongsTo(OfferClientActivity::class, 'id_client_activity'); }
    public function workflow(): BelongsTo { return $this->belongsTo(OfferWorkflow::class, 'id_workflow'); }
    public function formula(): BelongsTo { return $this->belongsTo(OfferFormula::class, 'id_formula'); }

    public function offerCompetitors(): HasMany
    {
        return $this->hasMany(OfferCompetitor::class, 'id_offer');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OfferDocument::class, 'offer_id');
    }
}
