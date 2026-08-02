# 🚀 CV Backoffice

> Applicazione web per la gestione centralizzata di una flotta di mezzi di pubblica assistenza.
> Guasti, manutenzioni, scadenze, chilometraggi e dotazioni di bordo in un unico pannello.

![GitHub license](https://img.shields.io/github/license/mirkobechini/CV_Backoffice)

---

## 🌟 Caratteristiche principali

- **Gestione completa del parco mezzi**: anagrafica veicoli, marche, modelli, tipologie e documenti
- **Flusso guasti e manutenzioni**: dalla segnalazione alla chiusura intervento, con collegamento polimorfico tra guasti, scadenze e appuntamenti in officina
- **Controllo scadenze e dotazioni**: revisioni ministeriali, ossigeno, tagliando, cinghia distribuzione, assicurazione — con stato automatico basato su data e km
- **Chilometraggi**: rilevazione mensile bulk, storico, integrazione con scadenze km
- **Dashboard interattiva**: statistiche, scadenze imminenti, guasti aperti, equipaggiamento incompleto
- **Calendario appuntamenti**: vista mese/settimana con colori per tipo attività
- **Export PDF e CSV**: scheda veicolo PDF, export CSV per tutte le entità
- **API REST**: 11 endpoint protetti da token (Sanctum)
- **Audit log**: tracciamento completo di tutte le modifiche
- **Notifiche email**: report giornaliero/settimanale/mensile configurabile
- **Rate limiting**: protezione su login e route admin
- **Tema chiaro/scuro**: persistente in localStorage
- **166 test, 330 assertions — tutti verdi** ✅

---

## 🛠️ Tech Stack

| Tecnologia | Scopo |
| :--------- | :---- |
| **Laravel 11 (PHP 8.2+)** | Core applicativo e logica backend |
| **Blade + Bootstrap 5 (Breeze)** | Interfaccia amministrativa |
| **Livewire 4** | Componenti dinamici (VehicleSelect) |
| **MySQL / SQLite** | Persistenza dati |
| **Laravel Sanctum** | API token authentication |
| **spatie/laravel-activitylog** | Audit logging |
| **DomPDF** | Export PDF |
| **FullCalendar** | Calendario appuntamenti |

---

## 🚀 Quick Start

### Requisiti

- PHP 8.2+
- Composer
- Node.js 18+ e npm
- MySQL (oppure SQLite per sviluppo locale)

### Installazione

```bash
git clone https://github.com/mirkobechini/CV_Backoffice.git
cd CV_Backoffice
composer install
npm install
```

### Configurazione

```bash
cp .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
php artisan import:car-data
```

### Avvio

```bash
# Due terminali separati:
npm run dev
php artisan serve
```

Apri il browser su `http://127.0.0.1:8000`.

---

## 📸 Screenshot

<!-- Aggiungi qui screenshot dell'app -->

---

## 🧪 Test

```bash
php artisan test
# 166 tests, 330 assertions — all green ✅
```

---

## 📂 Struttura del progetto

```text
.
|-- app/
|-- bootstrap/
|-- config/
|-- database/
|-- public/
|-- resources/
|-- routes/
|-- tests/
|-- README.md
|-- composer.json
|-- package.json
```

---

## 🗺️ Roadmap

### 🔜 Prossimi step

- [ ] Notifiche in-app (badge e toast nella navbar)
- [ ] Gestione utenti e ruoli da backoffice
- [ ] Fulltext search su volumi elevati
- [ ] App mobile nativa (via API REST)

---

## 👤 Contatti

**Sviluppatore:** Bechini Mirko  
**Email:** mirkobechini@gmail.com  
**GitHub:** [github.com/mirkobechini](https://github.com/mirkobechini)

---

## 📄 Licenza

MIT

---

## 📧 Contatti

Mirko Bechini - LinkedIn: https://www.linkedin.com/in/mirko-bechini-892202252
Mail: mirkobechini@gmail.com
Link progetto: https://github.com/mirkobechini/CV_Backoffice
