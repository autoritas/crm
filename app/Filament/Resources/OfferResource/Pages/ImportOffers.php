<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Filament\Resources\OfferResource;
use App\Models\ClientAlias;
use App\Models\Offer;
use App\Models\OfferStatus;
use App\Models\OfferType;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\WithFileUploads;

class ImportOffers extends Page
{
    use WithFileUploads;

    protected static string $resource = OfferResource::class;

    protected static string $view = 'filament.pages.import-offers';

    protected static ?string $title = 'Importar Ofertas';

    public $csv_file;
    public string $delimiter = ',';

    public int $imported = 0;
    public int $skipped = 0;
    public array $importErrors = [];
    public bool $showResults = false;

    protected function getFormStatePath(): ?string
    {
        return null;
    }

    public function import(): void
    {
        $this->validate([
            'csv_file' => 'required|file|max:65536',
            'delimiter' => 'required|string',
        ]);

        $companyId = (int) session('current_company_id', 1);
        $fullPath = $this->csv_file->getRealPath();

        if (!$fullPath || !file_exists($fullPath)) {
            Notification::make()->title('No se pudo acceder al archivo')->danger()->send();
            return;
        }

        $handle = fopen($fullPath, 'r');
        $header = fgetcsv($handle, 0, $this->delimiter);

        if (!$header) {
            fclose($handle);
            Notification::make()->title('No se pudo leer la cabecera del CSV')->danger()->send();
            return;
        }

        // Limpiar BOM y espacios
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h))), $header);

        // Mapeo CSV -> campos BD
        $columnMap = [
            'id' => '_original_id',
            'codigo_proyecto' => 'codigo_proyecto',
            'cliente' => 'cliente',
            'proyecto' => 'proyecto',
            'objeto' => 'objeto',
            'sector' => 'sector',
            'tipo_licitacion' => '_tipo_text',
            'fecha_presentacion' => 'fecha_presentacion',
            'importe_licitacion' => 'importe_licitacion',
            'importe_estimado' => 'importe_estimado',
            'duracion_meses' => 'duracion_meses',
            'estado' => '_estado_text',
            'temperatura' => 'temperatura',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];

        // Cargar lookups
        $statuses = OfferStatus::where('id_company', $companyId)
            ->pluck('id', 'status')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
            ->toArray();

        $types = OfferType::where('id_company', $companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
            ->toArray();

        $defaultStatusId = $statuses['pendiente'] ?? null;

        $this->imported = 0;
        $this->skipped = 0;
        $this->importErrors = [];
        $row = 1;

        while (($line = fgetcsv($handle, 0, $this->delimiter)) !== false) {
            $row++;

            if (count($line) !== count($header)) {
                $this->importErrors[] = "Fila {$row}: numero de columnas no coincide";
                $this->skipped++;
                continue;
            }

            $csvRow = array_combine($header, $line);
            $record = ['id_company' => $companyId];

            foreach ($columnMap as $csvCol => $dbField) {
                if (!isset($csvRow[$csvCol])) continue;

                $value = trim($csvRow[$csvCol]);
                if ($value === '' || $value === 'NULL' || $value === 'null') {
                    $value = null;
                }

                if ($dbField === '_original_id') {
                    continue;
                } elseif ($dbField === '_estado_text') {
                    $key = $value ? strtolower(str_replace('_', ' ', $value)) : null;
                    $record['id_offer_status'] = $key ? ($statuses[$key] ?? $defaultStatusId) : $defaultStatusId;
                } elseif ($dbField === '_tipo_text') {
                    $key = $value ? strtolower(trim($value)) : null;
                    $record['id_offer_type'] = $key ? ($types[$key] ?? null) : null;
                } elseif (in_array($dbField, ['importe_licitacion', 'importe_estimado'])) {
                    $record[$dbField] = $value !== null ? (float) str_replace(',', '.', $value) : null;
                } elseif ($dbField === 'duracion_meses') {
                    $record[$dbField] = $value !== null ? (int) $value : null;
                } elseif ($dbField === 'temperatura') {
                    $record[$dbField] = $value ? strtolower($value) : null;
                } else {
                    $record[$dbField] = $value;
                }
            }

            // Vincular cliente via aliases
            if (!empty($record['cliente'])) {
                $record['id_client'] = ClientAlias::resolveClientId($companyId, $record['cliente']);
            }

            try {
                Offer::create($record);
                $this->imported++;
            } catch (\Exception $e) {
                $this->importErrors[] = "Fila {$row}: " . $e->getMessage();
                $this->skipped++;
            }
        }

        fclose($handle);

        $this->showResults = true;
        $this->csv_file = null;

        Notification::make()
            ->title("Importacion completada: {$this->imported} registros importados" . ($this->skipped ? ", {$this->skipped} omitidos" : ''))
            ->success()
            ->send();
    }
}
