<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Filament\Resources\OfferResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateOffer extends CreateRecord
{
    protected static string $resource = OfferResource::class;

    protected function afterCreate(): void
    {
        $offer = $this->record;

        if (empty($offer->codigo_proyecto)) {
            $year = $offer->fecha_presentacion
                ? Carbon::parse($offer->fecha_presentacion)->year
                : now()->year;
            $offer->update(['codigo_proyecto' => $year . str_pad($offer->id, 6, '0', STR_PAD_LEFT)]);
        }
    }
}
