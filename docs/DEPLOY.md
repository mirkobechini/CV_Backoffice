# Deploy to Laravel Cloud

## Prerequisites

- A [Laravel Cloud](https://cloud.laravel.com) account (free tier is enough)
- The repository on GitHub

## Steps

1. **Create a new project** on Laravel Cloud and connect the GitHub repository
2. **Set the environment variables** in the Laravel Cloud control panel:

    | Variable                | Value                                    | Notes                                                    |
    | :------------------------ | :----------------------------------------- | :---------------------------------------------------------- |
    | `APP_ENV`               | `production`                             |                                                            |
    | `APP_DEBUG`             | `false`                                  |                                                            |
    | `APP_URL`               | `https://your-domain.laravel.cloud`      |                                                            |
    | `DB_CONNECTION`         | `mysql`                                  | Laravel Cloud provides MySQL                               |
    | `APP_LOCALE`            | `it`                                     |                                                            |
    | `MAIL_MAILER`           | `log`                                    | For testing, then switch to SMTP (Laravel Cloud injects it via `MAIL_URL` if you enable the email add-on) |
    | `OPENROUTER_API_KEY`    | your OpenRouter key                      | Required for the registration document scan; without it the feature fails gracefully, the rest of the app is unaffected |
    | `UPLOADS_DISK`          | `s3`                                     | **Required.** User-uploaded files (registration cards, fault photos) must not use the `public` (local disk) driver — Laravel Cloud's compute doesn't serve files written to local disk, so they 404 even after `storage:link`. See `R2_*` below. |
    | `R2_ACCESS_KEY_ID`      | from your Cloudflare R2 API token        |                                                            |
    | `R2_SECRET_ACCESS_KEY`  | from your Cloudflare R2 API token        |                                                            |
    | `R2_BUCKET`             | your R2 bucket name                      |                                                            |
    | `R2_ENDPOINT`           | `https://<account_id>.r2.cloudflarestorage.com` | Cloudflare account-specific S3 API endpoint         |
    | `R2_URL`                | your bucket's public URL or custom domain | Used to build the links shown to users (e.g. "Apri file") |

3. **After deploying**, open the Laravel Cloud terminal and run:

    ```bash
    php artisan migrate --seed
    php artisan import:car-data
    php artisan make:admin
    ```

4. **Follow the interactive prompts** of `make:admin` to create the first admin

    Alternatively, in non-interactive mode (CI/CD):

    ```bash
    php artisan make:admin --email="your@email.com" --password="secure-password"
    ```

5. **Configure the scheduler** (for automatic email reports):
    - On Laravel Cloud, add a cron job that runs `php artisan schedule:run` every minute
    - Or use Laravel Cloud's built-in worker

## Useful commands

| Command                                          | What it does                                                    |
| :------------------------------------------------ | :------------------------------------------------------------------ |
| `php artisan make:admin`                         | Creates the first admin user (lead of the default group)         |
| `php artisan import:car-data`                    | Imports car brands and models                                    |
| `php artisan app:send-summary-report`            | Sends a manual report                                             |
| `php artisan app:generate-notifications`         | Generates in-app notifications (deadlines, faults, equipment)     |
| `php artisan app:generate-notifications --email` | Generates notifications + sends automatic emails                  |
| `php artisan app:backup-database`                | Creates a JSON database backup                                    |
| `php artisan schedule:run`                       | Runs scheduled commands                                           |
