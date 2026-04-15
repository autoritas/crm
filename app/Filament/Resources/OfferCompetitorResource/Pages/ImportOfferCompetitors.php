<?php

namespace App\Filament\Resources\OfferCompetitorResource\Pages;

use App\Filament\Resources\OfferCompetitorResource;
use App\Models\CompetitorAlias;
use App\Models\Offer;
use App\Models\OfferCompetitor;
use App\Models\OfferCompetitorScore;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\WithFileUploads;

class ImportOfferCompetitors extends Page
{
    use WithFileUploads;

    protected static string $resource = OfferCompetitorResource::class;
    protected static string $view = 'filament.pages.import-generic';
    protected static ?string $title = 'Importar Competidores';

    public $csv_file;
    public string $delimiter = ',';
    public string $columnHelp = 'oferta_id, competitor_nombre, admision, razon_exclusion, tecnico, economico, objetivo_real, objetivo_fake, precio';
    public string $backUrl = '';
    public int $imported = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public array $importErrors = [];
    public bool $showResults = false;

    protected function getFormStatePath(): ?string { return null; }

    public function mount(): void { $this->backUrl = OfferCompetitorResource::getUrl('index'); }

    public function import(): void
    {
        $this->validate(['csv_file' => 'required|file|max:65536', 'delimiter' => 'required|string']);

        $companyId = (int) session('current_company_id', 1);
        $handle = fopen($this->csv_file->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h))), fgetcsv($handle, 0, $this->delimiter));

        $this->imported = 0; $this->updated = 0; $this->skipped = 0; $this->importErrors = []; $row = 1;

        while (($line = fgetcsv($handle, 0, $this->delimiter)) !== false) {
            $row++;
            if (count($line) !== count($header)) { $this->skipped++; continue; }

            $csvRow = array_combine($header, $line);
            $offerId = trim($csvRow['oferta_id'] ?? $csvRow['id'] ?? '');
            $nombre = trim($csvRow['competitor_nombre'] ?? $csvRow['nombre'] ?? '');

            if (empty($offerId) || empty($nombre)) {
                $this->importErrors[] = "Fila {$row}: oferta_id o nombre vacio";
                $this->skipped++;
                continue;
            }

            // Verificar que la oferta existe y es de la empresa
            $offer = Offer::where('id', $offerId)->where('company_id', $companyId)->first();
            if (!$offer) { $this->importErrors[] = "Fila {$row}: oferta {$offerId} no encontrada"; $this->skipped++; continue; }

            // Resolver competidor normalizado via aliases
            $competitorId = CompetitorAlias::resolveCompetitorId($companyId, $nombre);

            $admision = trim($csvRow['admision'] ?? 'Pendiente');
            $razonExclusion = trim($csvRow['razon_exclusion'] ?? '');
            if ($razonExclusion === '' || $razonExclusion === 'NULL') $razonExclusion = null;

            try {
                $oc = OfferCompetitor::create([
                    'id_offer' => $offerId,
                    'competitor_nombre' => $nombre,
                    'id_competitor' => $competitorId,
                    'admision' => $admision ?: 'Pendiente',
                    'razon_exclusion' => $razonExclusion,
                ]);

                // Crear scores si hay datos
                $scoreData = [];
                foreach (['tecnico', 'economico', 'objetivo_real', 'objetivo_fake', 'precio'] as $sf) {
                    if (isset($csvRow[$sf])) {
                        $val = trim($csvRow[$sf]);
                        $scoreData[$sf] = ($val === '' || $val === 'NULL') ? null : (float) str_replace(',', '.', $val);
                    }
                }
                if (array_filter($scoreData, fn ($v) => $v !== null)) {
                    $scoreData['id_offer_competitor'] = $oc->id;
                    OfferCompetitorScore::create($scoreData);
                }

                $this->imported++;
            } catch (\Exception $e) {
                $this->importErrors[] = "Fila {$row}: " . $e->getMessage();
                $this->skipped++;
            }
        }

        fclose($handle);
        $this->showResults = true;
        $this->csv_file = null;
        Notification::make()->title("Competidores importados: {$this->imported}")->success()->send();
    }
}
