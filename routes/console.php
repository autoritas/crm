<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reconcilia offers.id_workflow con la columna real de la tarea Kanboard.
// Cubre movimientos hechos directamente en el tablero que el CRM no habria
// detectado (el sentido CRM -> Kanboard ya lo cubre OfferObserver).
Schedule::command('kanboard:sync-workflows')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Descarga pliegos desde la plataforma (PLACSP, PSCP...) y los adjunta a
// la tarea Kanboard de cada oferta. Sin esto la IA no puede analizar Go/No Go.
// Se limita al lote de ofertas que AUN no tienen pliegos adjuntados.
Schedule::command('offers:sync-documents --pending-only --limit=25')
    ->everyTenMinutes()
    ->withoutOverlapping(20) // bloquea 20 min por si una oferta es lenta
    ->runInBackground();
