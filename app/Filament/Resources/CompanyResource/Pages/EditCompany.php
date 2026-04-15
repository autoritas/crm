<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\CompanySetting;
use Filament\Resources\Pages\EditRecord;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    /**
     * Campos del form que pertenecen a company_settings (BD local),
     * no a core.companies.
     */
    private const SETTINGS_FIELDS = [
        'slug',
        'logo_path',
        'icon_path',
        'primary_color',
        'kanboard_project_id',
        'kanboard_default_category_id',
        'kanboard_default_owner_id',
        'go_nogo_model',
    ];

    /**
     * No header actions: no se elimina ni se modifica Core desde aqui.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Al cargar el form: fusiona la fila de core.companies con su
     * company_settings local (si existe).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $settings = CompanySetting::firstOrNew(['company_id' => $this->record->id]);
        foreach (self::SETTINGS_FIELDS as $f) {
            $data[$f] = $settings->{$f};
        }
        return $data;
    }

    /**
     * Antes de guardar: extraer los campos de settings y persistirlos
     * en company_settings. El EditRecord guardara el resto contra Company,
     * pero como Company tiene $fillable=[], no escribira nada en Core.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $settingsData = [];
        foreach (self::SETTINGS_FIELDS as $f) {
            if (array_key_exists($f, $data)) {
                $settingsData[$f] = $data[$f];
                unset($data[$f]);
            }
        }

        CompanySetting::updateOrCreate(
            ['company_id' => $this->record->id],
            $settingsData,
        );

        return $data;
    }
}
