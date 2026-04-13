<?php

namespace App\Filament\Resources\OfferCriteriaResource\Pages;

use App\Filament\Resources\OfferCriteriaResource;
use App\Models\Offer;
use App\Models\OfferFormula;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\WithFileUploads;

class ImportOfferCriteria extends Page
{
    use WithFileUploads;

    protected static string $resource = OfferCriteriaResource::class;
    protected static string $view = 'filament.pages.import-generic';
    protected static ?string $title = 'Importar Criterios';

    public $csv_file;
    public string $delimiter = ',';
    public string $columnHelp = 'oferta_id, peso_economica, peso_tecnica, peso_objetiva_real, peso_objetiva_fake, formula';
    public string $backUrl = '';
    public int $imported = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public array $importErrors = [];
    public bool $showResults = false;

    protected function getFormStatePath(): ?string { return null; }

    public function mount(): void { $this->backUrl = OfferCriteriaResource::getUrl('index'); }

    public function import(): void
    {
        $this->validate(['csv_file' => 'required|file|max:65536', 'delimiter' => 'required|string']);

        $companyId = (int) session('current_company_id', 1);
        $handle = fopen($this->csv_file->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h))), fgetcsv($handle, 0, $this->delimiter));

        $formulas = OfferFormula::where('id_company', $companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
            ->toArray();

        $this->imported = 0; $this->updated = 0; $this->skipped = 0; $this->importErrors = []; $row = 1;

        while (($line = fgetcsv($handle, 0, $this->delimiter)) !== false) {
            $row++;
            if (count($line) !== count($header)) { $this->skipped++; continue; }

            $csvRow = array_combine($header, $line);
            $offerId = trim($csvRow['oferta_id'] ?? $csvRow['id'] ?? '');
            if (empty($offerId)) { $this->importErrors[] = "Fila {$row}: sin oferta_id"; $this->skipped++; continue; }

            $offer = Offer::where('id', $offerId)->where('id_company', $companyId)->first();
            if (!$offer) { $this->importErrors[] = "Fila {$row}: oferta {$offerId} no encontrada"; $this->skipped++; continue; }

            $data = [];
            foreach (['peso_economica', 'peso_tecnica', 'peso_objetiva_real', 'peso_objetiva_fake'] as $f) {
                if (isset($csvRow[$f])) {
                    $val = trim($csvRow[$f]);
                    $data[$f] = ($val === '' || $val === 'NULL') ? null : (float) str_replace(',', '.', $val);
                }
            }
            if (isset($csvRow['formula'])) {
                $fKey = strtolower(trim($csvRow['formula']));
                $data['id_formula'] = ($fKey === '' || $fKey === 'null') ? null : ($formulas[$fKey] ?? null);
            }

            try { $offer->update($data); $this->updated++; }
            catch (\Exception $e) { $this->importErrors[] = "Fila {$row}: " . $e->getMessage(); $this->skipped++; }
        }

        fclose($handle);
        $this->showResults = true;
        $this->csv_file = null;
        Notification::make()->title("Criterios actualizados: {$this->updated}")->success()->send();
    }
}
