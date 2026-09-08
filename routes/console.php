<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Consulta y reintento automático de facturas pendientes ante el SRI cada 5 minutos
Schedule::command('sri:consultar-pendientes')->everyFiveMinutes()->withoutOverlapping();

