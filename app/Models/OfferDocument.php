<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferDocument extends Model
{
    protected $connection = 'mysql';

    use BelongsToCompany;

    protected $fillable = [
        'offer_id', 'company_id', 'provider', 'source_url',
        'filename', 'mime', 'sha256', 'bytes',
        'kanboard_task_id', 'kanboard_file_id',
        'status', 'error',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'offer_id');
    }
}
