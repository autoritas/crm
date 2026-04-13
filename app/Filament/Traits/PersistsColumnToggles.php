<?php

namespace App\Filament\Traits;

use App\Models\UserTablePreference;

trait PersistsColumnToggles
{
    public function updatedTableColumnToggleFormData(): void
    {
        $userId = auth()->id();
        if (!$userId) return;

        $tableKey = static::$resource::getSlug();
        $toggleState = $this->tableColumnToggleFormData ?? [];

        UserTablePreference::saveToggledColumns($userId, $tableKey, $toggleState);
    }

    public function getDefaultTableColumnToggleState(): array
    {
        $userId = auth()->id();
        if (!$userId) return [];

        $tableKey = static::$resource::getSlug();
        $saved = UserTablePreference::getToggledColumns($userId, $tableKey);

        return $saved ?? [];
    }
}
