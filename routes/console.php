<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Il report riassuntivo è personale per account: gira ogni giorno e il
// comando stesso decide, per ciascun destinatario, se "oggi" è il suo
// giorno di invio in base alla propria frequenza (daily/weekly/monthly).
//
// onFailure() logga su storage/logs/laravel.log: prima un fallimento del
// comando (eccezione, crash) restava visibile solo nell'output dello
// scheduler stesso, che senza un canale di notifica dedicato non arrivava
// mai a nessuno — un'esecuzione fallita passava inosservata per settimane.
Schedule::command('app:send-summary-report')
    ->dailyAt('8:00')
    ->onFailure(fn () => Log::error('Scheduler: app:send-summary-report è terminato con un errore.'));

// Notifiche in-app + email automatiche per scadenze, guasti, attrezzature e
// appuntamenti in arrivo: anche qui ogni utente admin/sottocapo viene
// valutato con le proprie preferenze (giorni di preavviso, tipi di evento).
Schedule::command('app:generate-notifications --email')
    ->dailyAt('8:15')
    ->onFailure(fn () => Log::error('Scheduler: app:generate-notifications è terminato con un errore.'));
