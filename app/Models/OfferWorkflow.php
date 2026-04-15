<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferWorkflow extends Model
{
    protected $connection = 'mysql';

    use BelongsToCompany;
    protected $fillable = ['company_id', 'name', 'color', 'sort_order'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
