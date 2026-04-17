<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferWorkflow extends Model
{
    protected $connection = 'mysql';

    use BelongsToCompany;
    protected $fillable = [
        'company_id', 'name', 'color', 'sort_order',
        'kanboard_column_id', 'description',
        'closed_offer_status_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Estado que adopta la oferta cuando la tarea de Kanboard se cierra
     * estando en esta fase (ej: PROSPECTS -> Descartado, EN DECISION -> Perdido).
     */
    public function closedOfferStatus(): BelongsTo
    {
        return $this->belongsTo(OfferStatus::class, 'closed_offer_status_id');
    }
}
