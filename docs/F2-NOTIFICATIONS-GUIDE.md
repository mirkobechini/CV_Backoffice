# 📬 F2 — Email notification system

> **Goal:** Send periodic reports and timely reminders via email for deadlines, faults and appointments.
>
> **New concepts:** Artisan commands, Scheduler, Mail, Notifications in Laravel

---

## Table of Contents

1. [General architecture](#1-general-architecture)
2. [Artisan commands](#2-artisan-commands)
3. [Mail (Mailable)](#3-mail-mailable)
4. [Scheduler](#4-scheduler)
5. [DB-configurable cadence](#5-db-configurable-cadence)
6. [Implementation plan](#6-implementation-plan)

---

## 1. General architecture

```
┌─────────────────┐     ┌──────────────────────┐     ┌──────────────┐
│  routes/console  │────>│ SendSummaryReport    │────>│  ReportMail  │
│  (Scheduler)     │     │ (Artisan command)    │     │  (Mailable)  │
└─────────────────┘     └──────────────────────┘     └──────┬───────┘
                                                             │
                                                             ▼
                                                       ┌──────────────┐
                                                       │  User via    │
                                                       │    Email     │
                                                       └──────────────┘
```

### Components

| Component            | Role                                        | File                                          |
| --------------------- | -------------------------------------------- | ----------------------------------------------- |
| **Scheduler**        | Triggers the command every day at 8:00 AM  | `routes/console.php`                          |
| **Artisan command**  | Gathers the data and calls the mail          | `app/Console/Commands/SendSummaryReport.php`  |
| **Mailable**         | Builds the email body (HTML)                 | `app/Mail/ReportMail.php`                     |
| **Config**           | Configurable cadence (table or config)       | `config/notifications.php` or DB table        |

---

## 2. Artisan commands

### What they are

Artisan commands are PHP classes you can run with `php artisan command-name`. They're used for "background" operations such as: clearing cache, sending emails, generating reports.

### Creating a command

```bash
php artisan make:command SendSummaryReport
```

This creates `app/Console/Commands/SendSummaryReport.php`.

### Basic structure

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendSummaryReport extends Command
{
    // The command name used from the terminal
    // php artisan app:send-summary-report
    protected $signature = 'app:send-summary-report';

    // Description (shown in php artisan list)
    protected $description = 'Sends a summary report of deadlines and faults via email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // ← This is where you'll write the logic
        $this->info('Report sent successfully!');
    }
}
```

### Testing it

```bash
php artisan app:send-summary-report
```

You should see the message "Report sent successfully!"

### Signature with parameters

If you want to pass parameters (e.g. the recipient's email):

```php
protected $signature = 'app:send-summary-report {email? : Recipient email}';
```

Then in `handle()`:

```php
$email = $this->argument('email') ?? config('notifications.report_email');
```

---

## 3. Mail (Mailable)

### What they are

Mailables are classes that represent an email. They define the **subject** and the **view** (HTML content).

### Mail configuration

First of all, you need to configure an SMTP server. For development, use **Mailtrap** (free):

1. Go to [mailtrap.io](https://mailtrap.io) and sign up
2. Get the SMTP credentials
3. Put them in `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@cvbackoffice.it"
MAIL_FROM_NAME="CV Backoffice"
```

### Creating a Mailable

```bash
php artisan make:mail ReportMail
```

Creates `app/Mail/ReportMail.php`.

### Basic structure

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

    public $data; // ← the data you pass to the template

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Email subject.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📋 Daily report - CV Backoffice',
        );
    }

    /**
     * View to use for the email body.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.report',
        );
    }
}
```

### Email view

Create `resources/views/emails/report.blade.php`:

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
    <h1>📋 Daily report</h1>
    <p>{{ $data['date'] }}</p>

    <div class="stat stat-warning">
        <strong>{{ $data['upcomingDeadlinesCount'] }}</strong> upcoming deadlines
    </div>
    <div class="stat stat-danger">
        <strong>{{ $data['openIssuesCount'] }}</strong> open faults
    </div>

    @if (!empty($data['deadlinesToday']))
        <h2>⏰ Today's deadlines</h2>
        <ul>
            @foreach ($data['deadlinesToday'] as $d)
                <li>{{ $d['vehicle'] }} — {{ $d['type'] }}</li>
            @endforeach
        </ul>
    @endif

    <hr>
    <p style="color: #999;">This email is generated automatically.</p>
</body>
</html>
```

### Sending it from the command

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
            ['vehicle' => 'AMB-001', 'type' => 'Inspection'],
        ],
    ];

    Mail::to('admin@example.com')->send(new ReportMail($data));

    $this->info('Report sent!');
}
```

---

## 4. Scheduler

### What it does

The scheduler triggers commands automatically at set times. It's defined in `routes/console.php`.

### Configuration

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:send-summary-report')
    ->dailyAt('8:00');
```

### Useful methods

```php
Schedule::command('...')->dailyAt('8:00');       // Every day at 8
Schedule::command('...')->weeklyOn(1, '8:00');   // Every Monday at 8
Schedule::command('...')->everyMinute();          // Every minute (for testing)
Schedule::command('...')->cron('0 8 * * *');     // Classic cron format
```

### Making it actually work

In production or local development, you need to run a process that "listens" to the scheduler:

```bash
php artisan schedule:work
```

This command **keeps running** and checks every minute whether there are commands to launch.

> **⚠️ Warning:** `php artisan schedule:work` must keep running continuously (keep it in a separate terminal).

### Do I need a cron job?

No! `php artisan schedule:work` is already enough. But if you prefer the traditional method, add this to your system:

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. DB-configurable cadence

### Why not use .env?

Because the user needs to be able to change the cadence from the app without touching configuration files.

### NotificationSetting model

```bash
php artisan make:model NotificationSetting -m
```

In the migration:

```php
Schema::create('notification_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->string('value');
    $table->timestamps();
});
```

In the seeder:

```php
DB::table('notification_settings')->insert([
    ['key' => 'report_frequency', 'value' => 'daily'],    // daily | weekly | never
    ['key' => 'report_email', 'value' => 'admin@example.com'],
    ['key' => 'reminder_days_before', 'value' => '7'],
]);
```

Then a **helper** to read the settings:

```php
// app/Helpers/NotificationHelper.php
function notification_setting(string $key, $default = null)
{
    return \App\Models\NotificationSetting::where('key', $key)->value('value') ?? $default;
}
```

And conditional logic in the scheduler:

```php
$frequency = notification_setting('report_frequency', 'daily');

Schedule::command('app:send-summary-report')
    ->when(fn() => $frequency === 'daily')
    ->dailyAt('8:00');

Schedule::command('app:send-summary-report')
    ->when(fn() => $frequency === 'weekly')
    ->weeklyOn(1, '8:00');
```

### CRUD for the settings

A simple controller is enough to edit `notification_settings` from the app:

```php
Route::get('/admin/notifications', [NotificationSettingController::class, 'edit'])
    ->name('admin.notifications.edit');
Route::patch('/admin/notifications', [NotificationSettingController::class, 'update'])
    ->name('admin.notifications.update');
```

---

## 6. Implementation plan

| Step | What to do                                              | Command/File                                     |
| ---- | --------------------------------------------------------- | --------------------------------------------------- |
| 1    | Configure Mailtrap in `.env`                             | `.env`                                           |
| 2    | Create the command                                       | `php artisan make:command SendSummaryReport`     |
| 3    | Write the logic in `handle()`                            | `app/Console/Commands/SendSummaryReport.php`     |
| 4    | Create the mail                                           | `php artisan make:mail ReportMail`               |
| 5    | Create the email view                                    | `resources/views/emails/report.blade.php`        |
| 6    | Test the command                                          | `php artisan app:send-summary-report`            |
| 7    | Add the scheduler                                         | `routes/console.php`                             |
| 8    | Create the model + migration for the settings            | `php artisan make:model NotificationSetting -m`  |
| 9    | Create a seeder for default values                       | `database/seeders/NotificationSettingSeeder.php` |
| 10   | Create a controller and view to edit the settings         | Simple CRUD                                      |
| 11   | Adapt the scheduler to the configured frequency           | `routes/console.php`                             |

---

## Glossary

| Term          | Meaning                                                         |
| ------------- | ----------------------------------------------------------------- |
| **Artisan**   | Laravel's CLI (`php artisan ...`)                                |
| **Scheduler** | System that runs commands at set times                          |
| **Mailable**  | Class that represents an email                                  |
| **SMTP**      | Protocol for sending emails                                     |
| **Mailtrap**  | Service that intercepts emails in development (doesn't send them for real) |
| **Cron**      | Unix system for scheduling commands                             |
