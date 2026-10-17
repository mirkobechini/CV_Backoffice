<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\EquipmentRevision;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Provider;
use App\Models\Tire;
use App\Models\TireChange;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    /**
     * Tipi di elemento (subject_type) senza un gruppo proprio, condivisi
     * tra tutti i gruppi — stesso catalogo visibile/modificabile ovunque
     * nell'app, mostrarne il log non rivela nulla di un altro gruppo.
     */
    private const SHARED_SUBJECT_TYPES = [
        Provider::class,
        VehicleType::class,
        EquipmentType::class,
    ];

    /**
     * Display a listing of the activity log.
     */
    public function index(Request $request)
    {
        $query = Activity::with('causer')->latest();
        $this->scopeToActiveGroup($query);

        // Filtro per tipo di elemento (subject_type): il campo log_name è
        // sempre "default" per tutti i modelli (nessuno lo personalizza),
        // quindi non è utilizzabile come filtro; il tipo reale dell'elemento
        // coinvolto (veicolo, guasto, scadenza...) è invece su subject_type.
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        // Filtro per descrizione (testo libero): attribute_changes è dove
        // spatie/laravel-activitylog v5 salva i valori prima/dopo (vedi
        // migrazione upgrade_activity_log_table_to_v5_schema), properties
        // resta per eventuali custom property aggiunte manualmente.
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('properties', 'like', "%{$search}%")
                    ->orWhere('attribute_changes', 'like', "%{$search}%");
            });
        }

        $activities = $query->paginate(50)->withQueryString();

        // Tipi di elemento disponibili per il filtro, con etichetta leggibile
        // — stessa visibilità per gruppo della query principale, altrimenti
        // l'elenco dei filtri suggerirebbe tipi presenti solo in altri gruppi.
        $subjectTypesQuery = Activity::whereNotNull('subject_type');
        $this->scopeToActiveGroup($subjectTypesQuery);
        $subjectTypes = $subjectTypesQuery
            ->distinct('subject_type')
            ->pluck('subject_type')
            ->values()
            ->mapWithKeys(fn ($type) => [$type => $this->subjectTypeLabel($type)])
            ->sort();

        return view('admin.activity-log.index', compact('activities', 'subjectTypes'));
    }

    /**
     * Limita la query alle attività visibili al gruppo attivo dell'utente:
     * whitelist esplicita, un subject_type alla volta (vedi
     * subjectVisibilityQueries()). Qualunque subject_type non mappato qui
     * — incluso un tipo futuro aggiunto senza aggiornare questo filtro —
     * resta escluso di default (fail closed): senza questo, il log
     * mostrava le attività di TUTTI i gruppi a chiunque lo aprisse (bug
     * segnalato 2026-10-17).
     */
    private function scopeToActiveGroup(Builder $query): void
    {
        $groupId = auth()->user()?->activeGroup()?->id;

        if (! $groupId) {
            $query->whereRaw('1 = 0');

            return;
        }

        $visibilityQueries = $this->subjectVisibilityQueries($groupId);

        $query->where(function ($outer) use ($visibilityQueries) {
            foreach ($visibilityQueries as $subjectType => $visibleQuery) {
                $outer->orWhere(fn ($q) => $q->where('subject_type', $subjectType)
                    ->whereIn('subject_id', $visibleQuery->select('id')));
            }
            $outer->orWhereIn('subject_type', self::SHARED_SUBJECT_TYPES);
        });
    }

    /**
     * Per ogni subject_type effettivamente loggato (modelli con
     * HasActivity, vedi i rispettivi model), una query che risolve gli id
     * visibili al gruppo indicato. withTrashed() sui modelli con
     * SoftDeletes: la storia di un veicolo/guasto eliminato resta
     * comunque "del gruppo", non va persa dal log solo perché il record
     * non esiste più. L'attrezzatura non assegnata resta visibile a
     * tutti, stesso criterio permissivo già usato ovunque nell'app
     * (ADR §10) per questo caso.
     *
     * @return array<class-string, Builder>
     */
    private function subjectVisibilityQueries(int $groupId): array
    {
        $byVehicleGroup = fn ($q) => $q->where('group_id', $groupId);
        $equipmentVisible = fn ($q) => $q->whereDoesntHave('vehicle')->orWhereHas('vehicle', $byVehicleGroup);

        return [
            Vehicle::class => Vehicle::withTrashed()->where('group_id', $groupId),
            Issue::class => Issue::withTrashed()->whereHas('vehicle', $byVehicleGroup),
            Deadline::class => Deadline::withTrashed()->whereHas('vehicle', $byVehicleGroup),
            MaintenanceRecord::class => MaintenanceRecord::withTrashed()->whereHas('vehicle', $byVehicleGroup),
            MileageLog::class => MileageLog::whereHas('vehicle', $byVehicleGroup),
            Tire::class => Tire::withTrashed()->whereHas('vehicle', $byVehicleGroup),
            TireChange::class => TireChange::withTrashed()->whereHas('vehicle', $byVehicleGroup),
            Equipment::class => Equipment::where($equipmentVisible),
            EquipmentRevision::class => EquipmentRevision::withTrashed()->whereHas('equipment', $equipmentVisible),
            EquipmentIssue::class => EquipmentIssue::withTrashed()->whereHas('equipment', $equipmentVisible),
            EquipmentMaintenanceRecord::class => EquipmentMaintenanceRecord::withTrashed()
                ->whereHas('equipments', $equipmentVisible),
        ];
    }

    /**
     * Etichetta in italiano per un subject_type (classe modello).
     */
    public static function subjectTypeLabel(?string $subjectType): string
    {
        return match ($subjectType) {
            Vehicle::class => __('Veicolo'),
            Issue::class => __('Guasto'),
            Deadline::class => __('Scadenza'),
            MaintenanceRecord::class => __('Appuntamento'),
            Equipment::class => __('Attrezzatura'),
            EquipmentType::class => __('Tipo attrezzatura'),
            VehicleType::class => __('Tipo veicolo'),
            MileageLog::class => __('Chilometraggio'),
            Provider::class => __('Officina'),
            Group::class => __('Gruppo'),
            User::class => __('Utente'),
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
                $date = Carbon::parse($value);

                return $date->format($date->format('H:i:s') === '00:00:00' ? 'd/m/Y' : 'd/m/Y H:i');
            } catch (\Throwable $e) {
                Log::warning("Activity log: valore \"{$value}\" simile a una data ma non parsabile: {$e->getMessage()}");

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
    public static function propertyRows(?Collection $properties): array
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
