<?php

namespace App\Observers;

use App\Models\CompetitorAlias;
use App\Models\OfferCompetitor;

class OfferCompetitorObserver
{
    public function creating(OfferCompetitor $record): void
    {
        $this->linkCompetitor($record);
    }

    public function updating(OfferCompetitor $record): void
    {
        if ($record->isDirty('competitor_nombre')) {
            $this->linkCompetitor($record);
        }
    }

    private function linkCompetitor(OfferCompetitor $record): void
    {
        if (empty($record->competitor_nombre)) return;

        $companyId = $record->offer?->company_id ?? (int) session('current_company_id', 1);
        $competitorId = CompetitorAlias::resolveCompetitorId($companyId, $record->competitor_nombre);

        if ($competitorId) {
            $record->id_competitor = $competitorId;
        }
    }
}
