@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ route('admin.vehicles.index') }}" class="btn btn-secondary">Torna alla dashboard</a>
            </div>
        </div>
        <h1 class="mb-4">⚙️ Impostazioni notifiche</h1>
        <div class="card my-0">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.notifications.update') }}" data-single-submit="true">
                    @csrf
                    @method('PATCH')

                    <section class="mb-4">
                        <h2 class="h5 mb-3">📧 Email destinatario</h2>
                        <div class="mb-3">
                            <label for="report_email" class="form-label">Indirizzo email per ricevere i report</label>
                            <input type="email" class="form-control @error('report_email') is-invalid @enderror"
                                id="report_email" name="report_email"
                                value="{{ old('report_email', $settings['report_email'] ?? '') }}" required>
                            @error('report_email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </section>

                    <section class="mb-4">
                        <h2 class="h5 mb-3">📅 Frequenza report</h2>
                        <div class="mb-3">
                            <label for="report_frequency" class="form-label">Ogni quanto ricevere il report riassuntivo</label>
                            <select class="form-select @error('report_frequency') is-invalid @enderror"
                                id="report_frequency" name="report_frequency" required>
                                <option value="daily" {{ old('report_frequency', $settings['report_frequency'] ?? '') == 'daily' ? 'selected' : '' }}>Ogni giorno</option>
                                <option value="weekly" {{ old('report_frequency', $settings['report_frequency'] ?? '') == 'weekly' ? 'selected' : '' }}>Ogni settimana (lunedì)</option>
                                <option value="monthly" {{ old('report_frequency', $settings['report_frequency'] ?? '') == 'monthly' ? 'selected' : '' }}>Ogni mese (1° giorno)</option>
                            </select>
                            @error('report_frequency')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </section>

                    <section class="mb-4">
                        <h2 class="h5 mb-3">⏰ Promemoria</h2>
                        <div class="mb-3">
                            <label for="reminder_days_before" class="form-label">Quanti giorni prima avvisare per le scadenze</label>
                            <input type="number" class="form-control @error('reminder_days_before') is-invalid @enderror"
                                id="reminder_days_before" name="reminder_days_before"
                                value="{{ old('reminder_days_before', $settings['reminder_days_before'] ?? '7') }}" min="1" max="90" required>
                            @error('reminder_days_before')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Le scadenze con data entro questo numero di giorni verranno incluse nel report come "in arrivo".</div>
                        </div>
                    </section>

                    <button type="submit" class="btn btn-primary" data-loading-text="Salvataggio...">Salva impostazioni</button>
                </form>
            </div>
        </div>
    </div>
@endsection
