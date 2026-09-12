<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the activity log.
     */
    public function index(Request $request)
    {
        $query = Activity::with('causer')->latest();

        // Filtro per tipo di elemento (subject_type): il campo log_name è
        // sempre "default" per tutti i modelli (nessuno lo personalizza),
        // quindi non è utilizzabile come filtro; il tipo reale dell'elemento
        // coinvolto (veicolo, guasto, scadenza...) è invece su subject_type.
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        // Filtro per descrizione (testo libero)
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('properties', 'like', "%{$search}%");
            });
        }

        $activities = $query->paginate(50)->withQueryString();

        // Tipi di elemento disponibili per il filtro, con etichetta leggibile.
        $subjectTypes = Activity::whereNotNull('subject_type')
            ->distinct('subject_type')
            ->pluck('subject_type')
            ->values()
            ->mapWithKeys(fn($type) => [$type => $this->subjectTypeLabel($type)])
            ->sort();

        return view('admin.activity-log.index', compact('activities', 'subjectTypes'));
    }

    /**
     * Etichetta in italiano per un subject_type (classe modello).
     */
    public static function subjectTypeLabel(?string $subjectType): string
    {
        return match ($subjectType) {
            \App\Models\Vehicle::class => __('Veicolo'),
            \App\Models\Issue::class => __('Guasto'),
            \App\Models\Deadline::class => __('Scadenza'),
            \App\Models\MaintenanceRecord::class => __('Appuntamento'),
            \App\Models\Equipment::class => __('Attrezzatura'),
            \App\Models\EquipmentType::class => __('Tipo attrezzatura'),
            \App\Models\VehicleType::class => __('Tipo veicolo'),
            \App\Models\MileageLog::class => __('Chilometraggio'),
            \App\Models\Provider::class => __('Officina'),
            \App\Models\Group::class => __('Gruppo'),
            \App\Models\User::class => __('Utente'),
            default => $subjectType ? class_basename($subjectType) : __('N/D'),
        };
    }

    /**
     * Etichetta in italiano per un nome di campo (chiave delle proprietà
     * registrate da Spatie Activitylog: attributes/old).
     */
    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'name' => __('Nome'),
            'description' => __('Descrizione'),
            'status' => __('Stato'),
            'type' => __('Tipo'),
            'activity_type' => __('Tipo attività'),
            'due_date' => __('Data di scadenza'),
            'event_date' => __('Data evento'),
            'appointment_date' => __('Data appuntamento'),
            'return_date' => __('Data restituzione'),
            'log_date' => __('Data rilevazione'),
            'mileage' => __('Chilometraggio'),
            'mileage_at_service' => __("Km all'appuntamento"),
            'last_mileage' => __('Ultimo chilometraggio'),
            'interval_km' => __('Intervallo km'),
            'interval_days' => __('Intervallo giorni'),
            'is_renewed' => __('Rinnovata'),
            'vehicle_id' => __('Veicolo'),
            'provider_id' => __('Officina'),
            'equipment_type_id' => __('Tipo attrezzatura'),
            'vehicle_type_id' => __('Tipo veicolo'),
            'brand_id' => __('Marca'),
            'car_model_id' => __('Modello'),
            'license_plate' => __('Targa'),
            'internal_code' => __('Sigla'),
            'fuel_type' => __('Alimentazione'),
            'serial_number' => __('Numero di serie'),
            'revision_date' => __('Data revisione'),
            'expiration_date' => __('Data scadenza'),
            'notes' => __('Note'),
            'photo' => __('Immagine'),
            'invite_code' => __('Codice invito'),
            'email' => __('Email'),
            'report_frequency' => __('Frequenza report'),
            'reminder_days_before' => __('Giorni di preavviso'),
            default => ucfirst(str_replace('_', ' ', $field)),
        };
    }

    /**
     * Formatta un valore di proprietà per la visualizzazione.
     */
    public static function formatPropertyValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? __('Sì') : __('No');
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?/', $value)) {
            try {
                $date = \Illuminate\Support\Carbon::parse($value);
                return $date->format($date->format('H:i:s') === '00:00:00' ? 'd/m/Y' : 'd/m/Y H:i');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        return (string) $value;
    }

    /**
     * Righe leggibili a partire dalle properties di un'attività (struttura
     * Spatie: {old: {...}, attributes: {...}}). Se l'attività ha valori
     * "prima" (un aggiornamento), mostra il confronto prima/dopo; se è una
     * creazione (nessun 'old'), mostra solo i valori impostati. I campi
     * puramente tecnici (timestamp) vengono esclusi perché cambiano ad ogni
     * salvataggio e non aggiungono informazione.
     *
     * @return array{mode: 'diff'|'flat', rows: array<int, array{field: string, before: string, after: string}>}
     */
    public static function propertyRows(?\Illuminate\Support\Collection $properties): array
    {
        if (! $properties || $properties->isEmpty()) {
            return ['mode' => 'flat', 'rows' => []];
        }

        $ignoredFields = ['created_at', 'updated_at', 'deleted_at'];

        $attributes = collect($properties->get('attributes', []));
        $old = collect($properties->get('old', []));

        // Se non c'è né 'attributes' né 'old' (formato non standard, es. log
        // manuale senza modello), mostriamo le properties così come sono.
        if ($attributes->isEmpty() && $old->isEmpty()) {
            $rows = $properties->except($ignoredFields)
                ->map(fn ($value, $field) => [
                    'field' => self::fieldLabel((string) $field),
                    'before' => '—',
                    'after' => self::formatPropertyValue($value),
                ])
                ->values()
                ->all();

            return ['mode' => 'flat', 'rows' => $rows];
        }

        $fields = $attributes->keys()->merge($old->keys())->unique()->diff($ignoredFields);
        $mode = $old->isEmpty() ? 'flat' : 'diff';

        $rows = $fields->map(fn ($field) => [
            'field' => self::fieldLabel($field),
            'before' => $old->has($field) ? self::formatPropertyValue($old->get($field)) : '—',
            'after' => $attributes->has($field) ? self::formatPropertyValue($attributes->get($field)) : '—',
        ])->values()->all();

        return ['mode' => $mode, 'rows' => $rows];
    }
}
