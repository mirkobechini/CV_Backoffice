# 📬 F2 — Sistema di notifiche email

> **Obiettivo:** Inviare report periodici e reminder puntuali via email per scadenze, guasti e appuntamenti.
>
> **Concetti nuovi:** Comandi Artisan, Scheduler, Mail, Notifications in Laravel

---

## Indice

1. [Architettura generale](#1-architettura-generale)
2. [Comandi Artisan](#2-comandi-artisan)
3. [Mail (Mailable)](#3-mail-mailable)
4. [Scheduler](#4-scheduler)
5. [Cadenza configurabile dal DB](#5-cadenza-configurabile-dal-db)
6. [Piano di implementazione](#6-piano-di-implementazione)

---

## 1. Architettura generale

```
┌─────────────────┐     ┌──────────────────────┐     ┌──────────────┐
│  routes/console  │────>│ SendSummaryReport    │────>│  ReportMail  │
│  (Scheduler)     │     │ (Comando Artisan)    │     │  (Mailable)  │
└─────────────────┘     └──────────────────────┘     └──────┬───────┘
                                                             │
                                                             ▼
                                                       ┌──────────────┐
                                                       │  Utente via  │
                                                       │    Email     │
                                                       └──────────────┘
```

### Componenti

| Componente          | Ruolo                                       | File                                         |
| ------------------- | ------------------------------------------- | -------------------------------------------- |
| **Scheduler**       | Fa partire il comando ogni giorno alle 8:00 | `routes/console.php`                         |
| **Comando Artisan** | Raccoglie i dati e chiama la mail           | `app/Console/Commands/SendSummaryReport.php` |
| **Mailable**        | Costruisce il corpo della mail (HTML)       | `app/Mail/ReportMail.php`                    |
| **Config**          | Cadenza configurabile (tabella o config)    | `config/notifications.php` o tabella DB      |

---

## 2. Comandi Artisan

### Cosa sono

I comandi Artisan sono classi PHP che puoi eseguire con `php artisan nome-comando`. Servono per operazioni "di background" tipo: pulire cache, inviare email, generare report.

### Creare un comando

```bash
php artisan make:command SendSummaryReport
```

Questo crea `app/Console/Commands/SendSummaryReport.php`.

### Struttura di base

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendSummaryReport extends Command
{
    // Come si chiama il comando da terminale
    // php artisan app:send-summary-report
    protected $signature = 'app:send-summary-report';

    // Descrizione (compare in php artisan list)
    protected $description = 'Invia un report riassuntivo delle scadenze e guasti via email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // ← Qui scriverai la logica
        $this->info('Report inviato con successo!');
    }
}
```

### Testarlo

```bash
php artisan app:send-summary-report
```

Dovresti vedere il messaggio "Report inviato con successo!"

### Signature con parametri

Se vuoi passare parametri (es. email destinatario):

```php
protected $signature = 'app:send-summary-report {email? : Email del destinatario}';
```

Poi nel `handle()`:

```php
$email = $this->argument('email') ?? config('notifications.report_email');
```

---

## 3. Mail (Mailable)

### Cosa sono

I Mailable sono classi che rappresentano un'email. Definiscono il **soggetto** e la **view** (contenuto HTML).

### Configurazione Mail

Prima di tutto, devi configurare un SMTP. Per sviluppo, usa **Mailtrap** (gratuito):

1. Vai su [mailtrap.io](https://mailtrap.io) e registrati
2. Prendi le credenziali SMTP
3. Mettile nel `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=il_tuo_username
MAIL_PASSWORD=la_tua_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@cvbackoffice.it"
MAIL_FROM_NAME="CV Backoffice"
```

### Creare un Mailable

```bash
php artisan make:mail ReportMail
```

Crea `app/Mail/ReportMail.php`.

### Struttura di base

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data; // ← i dati che passi al template

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Oggetto dell'email.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📋 Report giornaliero - CV Backoffice',
        );
    }

    /**
     * View da usare per il corpo dell'email.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.report',
        );
    }
}
```

### View dell'email

Crea `resources/views/emails/report.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .stat { display: inline-block; padding: 10px 20px; margin: 5px; border-radius: 8px; }
        .stat-warning { background: #fff3cd; }
        .stat-danger { background: #f8d7da; }
        .stat-success { background: #d4edda; }
    </style>
</head>
<body>
    <h1>📋 Report giornaliero</h1>
    <p>{{ $data['date'] }}</p>

    <div class="stat stat-warning">
        <strong>{{ $data['upcomingDeadlinesCount'] }}</strong> scadenze imminenti
    </div>
    <div class="stat stat-danger">
        <strong>{{ $data['openIssuesCount'] }}</strong> guasti aperti
    </div>

    @if (!empty($data['deadlinesToday']))
        <h2>⏰ Scadenze di oggi</h2>
        <ul>
            @foreach ($data['deadlinesToday'] as $d)
                <li>{{ $d['vehicle'] }} — {{ $d['type'] }}</li>
            @endforeach
        </ul>
    @endif

    <hr>
    <p style="color: #999;">Questa mail è generata automaticamente.</p>
</body>
</html>
```

### Come inviarla dal comando

```php
use App\Mail\ReportMail;
use Illuminate\Support\Facades\Mail;

public function handle()
{
    $data = [
        'date' => now()->format('d/m/Y'),
        'upcomingDeadlinesCount' => 5,
        'openIssuesCount' => 2,
        'deadlinesToday' => [
            ['vehicle' => 'AMB-001', 'type' => 'Revisione'],
        ],
    ];

    Mail::to('admin@example.com')->send(new ReportMail($data));

    $this->info('Report inviato!');
}
```

---

## 4. Scheduler

### Cosa fa

Lo scheduler fa partire i comandi automaticamente a orari prestabiliti. È definito in `routes/console.php`.

### Configurazione

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:send-summary-report')
    ->dailyAt('8:00');
```

### Metodi utili

```php
Schedule::command('...')->dailyAt('8:00');       // Ogni giorno alle 8
Schedule::command('...')->weeklyOn(1, '8:00');   // Ogni lunedì alle 8
Schedule::command('...')->everyMinute();          // Ogni minuto (per test)
Schedule::command('...')->cron('0 8 * * *');     // Formato cron classico
```

### Come farlo funzionare "davvero"

In produzione o sviluppo locale, devi far partire un processo che "ascolti" lo scheduler:

```bash
php artisan schedule:work
```

Questo comando **resta in esecuzione** e controlla ogni minuto se ci sono comandi da lanciare.

> **⚠️ Attenzione:** `php artisan schedule:work` deve stare in esecuzione continua (tienilo in un terminale separato).

### Ho bisogno di un cron job?

No! `php artisan schedule:work` è già sufficiente. Ma se preferisci il metodo tradizionale, aggiungi al tuo sistema:

```
* * * * * cd /percorso/progetto && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Cadenza configurabile dal DB

### Perché non usare .env?

Perché l'utente deve poter cambiare la cadenza dall'app senza toccare file di configurazione.

### Modello NotificationSetting

```bash
php artisan make:model NotificationSetting -m
```

Nella migration:

```php
Schema::create('notification_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->string('value');
    $table->timestamps();
});
```

Nel seeder:

```php
DB::table('notification_settings')->insert([
    ['key' => 'report_frequency', 'value' => 'daily'],    // daily | weekly | never
    ['key' => 'report_email', 'value' => 'admin@example.com'],
    ['key' => 'reminder_days_before', 'value' => '7'],
]);
```

Poi un **helper** per leggere le impostazioni:

```php
// app/Helpers/NotificationHelper.php
function notification_setting(string $key, $default = null)
{
    return \App\Models\NotificationSetting::where('key', $key)->value('value') ?? $default;
}
```

E nello scheduler logica condizionale:

```php
$frequency = notification_setting('report_frequency', 'daily');

Schedule::command('app:send-summary-report')
    ->when(fn() => $frequency === 'daily')
    ->dailyAt('8:00');

Schedule::command('app:send-summary-report')
    ->when(fn() => $frequency === 'weekly')
    ->weeklyOn(1, '8:00');
```

### Crud per le impostazioni

Basta un controller semplice per modificare `notification_settings` dall'app:

```php
Route::get('/admin/notifications', [NotificationSettingController::class, 'edit'])
    ->name('admin.notifications.edit');
Route::patch('/admin/notifications', [NotificationSettingController::class, 'update'])
    ->name('admin.notifications.update');
```

---

## 6. Piano di implementazione

| Step | Cosa fare                                               | Comando/File                                     |
| ---- | ------------------------------------------------------- | ------------------------------------------------ |
| 1    | Configurare Mailtrap nel `.env`                         | `.env`                                           |
| 2    | Creare il comando                                       | `php artisan make:command SendSummaryReport`     |
| 3    | Scrivere la logica nel `handle()`                       | `app/Console/Commands/SendSummaryReport.php`     |
| 4    | Creare la mail                                          | `php artisan make:mail ReportMail`               |
| 5    | Creare la view email                                    | `resources/views/emails/report.blade.php`        |
| 6    | Testare il comando                                      | `php artisan app:send-summary-report`            |
| 7    | Aggiungere lo scheduler                                 | `routes/console.php`                             |
| 8    | Creare model + migration per impostazioni               | `php artisan make:model NotificationSetting -m`  |
| 9    | Creare seeder per valori di default                     | `database/seeders/NotificationSettingSeeder.php` |
| 10   | Creare controller e view per modificare le impostazioni | CRUD semplice                                    |
| 11   | Adattare lo scheduler alla frequenza configurata        | `routes/console.php`                             |

---

## Glossario

| Termine       | Significato                                                      |
| ------------- | ---------------------------------------------------------------- |
| **Artisan**   | CLI di Laravel (`php artisan ...`)                               |
| **Scheduler** | Sistema che esegue comandi a orari prestabiliti                  |
| **Mailable**  | Classe che rappresenta un'email                                  |
| **SMTP**      | Protocollo per inviare email                                     |
| **Mailtrap**  | Servizio che intercetta email in sviluppo (non le invia davvero) |
| **Cron**      | Sistema Unix per schedulare comandi                              |
