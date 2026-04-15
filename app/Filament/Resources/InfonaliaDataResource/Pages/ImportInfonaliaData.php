<?php

namespace App\Filament\Resources\InfonaliaDataResource\Pages;

use App\Filament\Resources\InfonaliaDataResource;
use App\Models\InfonaliaData;
use App\Models\InfonaliaStatus;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\WithFileUploads;

class ImportInfonaliaData extends Page
{
    use WithFileUploads;

    protected static string $resource = InfonaliaDataResource::class;

    protected static string $view = 'filament.pages.import-infonalia';

    protected static ?string $title = 'Importar datos Infonalia';

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

        $columnMap = [
            'id' => '_original_id',
            'decision' => '_decision_text',
            'ia_decision' => '_ia_decision_text',
            'ia_motivo' => 'ia_motivo',
            'ia_fecha' => 'ia_fecha',
            'revisado_humano' => 'revisado_humano',
            'revisado_fecha' => 'revisado_fecha',
            'fecha_publicacion' => 'fecha_publicacion',
            'cliente' => 'cliente',
            'resumen_objeto' => 'resumen_objeto',
            'provincia' => 'provincia',
            'presupuesto' => 'presupuesto',
            'presentacion' => 'presentacion',
            'perfil_contratante' => 'perfil_contratante',
            'fecha_ingreso' => 'fecha_ingreso',
            'url' => 'url',
            'kanboard_task_id' => 'kanboard_task_id',
            'updated_at' => 'updated_at',
            'created_at' => 'created_at',
        ];

        $statuses = InfonaliaStatus::where('company_id', $companyId)
            ->pluck('id', 'status')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
            ->toArray();

        $defaultDecisionId = $statuses['pendiente'] ?? null;

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
            $record = ['company_id' => $companyId];

            foreach ($columnMap as $csvCol => $dbField) {
                if (!isset($csvRow[$csvCol])) continue;

                $value = trim($csvRow[$csvCol]);
                if ($value === '' || $value === 'NULL' || $value === 'null') {
                    $value = null;
                }

                if ($dbField === '_decision_text') {
                    $key = $value ? strtolower(str_replace('_', ' ', $value)) : null;
                    $record['id_decision'] = $key ? ($statuses[$key] ?? $defaultDecisionId) : $defaultDecisionId;
                } elseif ($dbField === '_ia_decision_text') {
                    $key = $value ? strtolower(str_replace('_', ' ', $value)) : null;
                    $record['id_ia_decision'] = $key ? ($statuses[$key] ?? null) : null;
                } elseif ($dbField === '_original_id') {
                    continue;
                } elseif ($dbField === 'revisado_humano') {
                    $record[$dbField] = (bool) $value;
                } elseif ($dbField === 'presupuesto') {
                    $record[$dbField] = $value !== null ? (float) str_replace(',', '.', $value) : null;
                } elseif ($dbField === 'kanboard_task_id') {
                    $record[$dbField] = $value !== null ? (int) $value : null;
                } else {
                    $record[$dbField] = $value;
                }
            }

            try {
                InfonaliaData::create($record);
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
