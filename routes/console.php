<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Il report riassuntivo è personale per account: gira ogni giorno e il
// comando stesso decide, per ciascun destinatario, se "oggi" è il suo
// giorno di invio in base alla propria frequenza (daily/weekly/monthly).
Schedule::command('app:send-summary-report')
    ->dailyAt('8:00');

// Notifiche in-app + email automatiche per scadenze, guasti, attrezzature e
// appuntamenti in arrivo: anche qui ogni utente admin/sottocapo viene
// valutato con le proprie preferenze (giorni di preavviso, tipi di evento).
Schedule::command('app:generate-notifications --email')
    ->dailyAt('8:15');
