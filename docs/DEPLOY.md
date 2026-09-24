# Deploy su Laravel Cloud

## Prerequisiti

- Un account [Laravel Cloud](https://cloud.laravel.com) (piano gratuito sufficiente)
- Il repository su GitHub

## Passi

1. **Crea un nuovo progetto** su Laravel Cloud e collega il repository GitHub
2. **Imposta le variabili d'ambiente** nel pannello di controllo Laravel Cloud:

    | Variabile             | Valore                                 | Note                                                  |
    | :--------------------- | :-------------------------------------- | :----------------------------------------------------- |
    | `APP_ENV`               | `production`                            |                                                          |
    | `APP_DEBUG`             | `false`                                  |                                                          |
    | `APP_URL`               | `https://il-tuo-dominio.laravel.cloud`   |                                                          |
    | `DB_CONNECTION`         | `mysql`                                  | Laravel Cloud fornisce MySQL                             |
    | `APP_LOCALE`            | `it`                                     |                                                          |
    | `MAIL_MAILER`           | `log`                                    | Per test, poi passa a SMTP (Laravel Cloud lo inietta via `MAIL_URL` se abiliti l'add-on email) |
    | `OPENROUTER_API_KEY`    | la tua chiave OpenRouter                 | Obbligatoria per la scansione del libretto; senza fallisce con un errore gestito, il resto dell'app non è impattato |

3. **Dopo il deploy**, apri il terminale di Laravel Cloud ed esegui:

    ```bash
    php artisan migrate --seed
    php artisan import:car-data
    php artisan make:admin
    ```

4. **Segui le istruzioni interattive** di `make:admin` per creare il primo admin

    In alternativa, in modalità non interattiva (CI/CD):

    ```bash
    php artisan make:admin --email="tua@email.com" --password="password-sicura"
    ```

5. **Configura lo scheduler** (per report email automatici):
    - Su Laravel Cloud, aggiungi un cron job che esegua `php artisan schedule:run` ogni minuto
    - Oppure usa il worker integrato di Laravel Cloud

## Comandi utili

| Comando                                          | Cosa fa                                                          |
| :------------------------------------------------ | :----------------------------------------------------------------- |
| `php artisan make:admin`                         | Crea il primo utente amministratore (capo del gruppo di default) |
| `php artisan import:car-data`                    | Importa marche e modelli auto                                    |
| `php artisan app:send-summary-report`            | Invia report manuale                                             |
| `php artisan app:generate-notifications`         | Genera notifiche in-app (scadenze, guasti, attrezzature)         |
| `php artisan app:generate-notifications --email` | Genera notifiche + invia email automatiche                       |
| `php artisan app:backup-database`                | Crea un backup del database in JSON                              |
| `php artisan schedule:run`                       | Esegue i comandi schedulati                                      |
