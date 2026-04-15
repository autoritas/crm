<?php

namespace App\Filament\Resources\OfferDatesResource\Pages;

use App\Filament\Resources\OfferDatesResource;
use App\Models\Offer;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\WithFileUploads;

class ImportOfferDates extends Page
{
    use WithFileUploads;

    protected static string $resource = OfferDatesResource::class;
    protected static string $view = 'filament.pages.import-generic';
    protected static ?string $title = 'Importar Fechas';

    public $csv_file;
    public string $delimiter = ',';
    public string $columnHelp = 'oferta_id, fecha_anuncio, fecha_publicacion, fecha_adjudicacion, fecha_formalizacion, fecha_fin_contrato';
    public string $backUrl = '';
    public int $imported = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public array $importErrors = [];
    public bool $showResults = false;

    protected function getFormStatePath(): ?string { return null; }

    public function mount(): void
    {
        $this->backUrl = OfferDatesResource::getUrl('index');
    }

    public function import(): void
    {
        $this->validate(['csv_file' => 'required|file|max:65536', 'delimiter' => 'required|string']);

        $companyId = (int) session('current_company_id', 1);
        $handle = fopen($this->csv_file->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h))), fgetcsv($handle, 0, $this->delimiter));

        $this->imported = 0; $this->updated = 0; $this->skipped = 0; $this->importErrors = []; $row = 1;

        $dateFields = ['fecha_anuncio', 'fecha_publicacion', 'fecha_adjudicacion', 'fecha_formalizacion', 'fecha_fin_contrato'];

        while (($line = fgetcsv($handle, 0, $this->delimiter)) !== false) {
            $row++;
            if (count($line) !== count($header)) { $this->importErrors[] = "Fila {$row}: columnas no coinciden"; $this->skipped++; continue; }

            $csvRow = array_combine($header, $line);
            $offerId = trim($csvRow['oferta_id'] ?? $csvRow['id'] ?? '');

            if (empty($offerId)) { $this->importErrors[] = "Fila {$row}: sin oferta_id"; $this->skipped++; continue; }

            $offer = Offer::where('id', $offerId)->where('company_id', $companyId)->first();
            if (!$offer) { $this->importErrors[] = "Fila {$row}: oferta {$offerId} no encontrada"; $this->skipped++; continue; }

            $data = [];
            foreach ($dateFields as $field) {
                if (isset($csvRow[$field])) {
                    $val = trim($csvRow[$field]);
                    $data[$field] = ($val === '' || $val === 'NULL') ? null : $val;
                }
            }

            try {
                $offer->update($data);
                $this->updated++;
            } catch (\Exception $e) {
                $this->importErrors[] = "Fila {$row}: " . $e->getMessage();
                $this->skipped++;
            }
        }

        fclose($handle);
        $this->showResults = true;
        $this->csv_file = null;
        Notification::make()->title("Fechas actualizadas: {$this->updated}")->success()->send();
    }
}
