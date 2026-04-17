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
