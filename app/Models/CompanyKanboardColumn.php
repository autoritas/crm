<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyKanboardColumn extends Model
{
    protected $fillable = ['id_company', 'kanboard_column_id', 'name', 'position', 'description'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'id_company');
    }
}
