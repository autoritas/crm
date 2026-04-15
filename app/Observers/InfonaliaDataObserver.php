<?php

namespace App\Observers;

use App\Models\ClientAlias;
use App\Models\InfonaliaData;

class InfonaliaDataObserver
{
    public function creating(InfonaliaData $record): void
    {
        $this->linkClient($record);
    }

    public function updating(InfonaliaData $record): void
    {
        if ($record->isDirty('cliente')) {
            $this->linkClient($record);
        }
    }

    private function linkClient(InfonaliaData $record): void
    {
        if (empty($record->cliente) || !$record->company_id) {
            return;
        }

        // Registra el nombre crudo en aliases y obtiene el client_id si está vinculado
        $clientId = ClientAlias::resolveClientId($record->company_id, $record->cliente);
        $record->id_client = $clientId;
    }
}
