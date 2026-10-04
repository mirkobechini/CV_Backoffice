<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Vehicle;
use Carbon\Carbon;

class DeadlineService
{
    public function __construct(
        private readonly MileageLogService $mileageLogService,
        private readonly DeadlineManualRenewalService $manualRenewalService,
    ) {
    }

    /**
     * Tipi periodici per cui l'aggiunta di una nuova scadenza rinnova
     * automaticamente quella precedente dello stesso veicolo.
     */
    private const AUTO_RENEW_TYPES = [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN];

    /**
     * Tipi per cui last_mileage rappresenta il km ALLA data di questa
     * scadenza (una volta rinnovata, due_date È la data della revisione):
     * l'unico caso in cui ha senso registrare quella coppia km+data nello
     * storico chilometraggi. Per tagliando/cinghia, invece, last_mileage è
     * il km dell'ULTIMO cambio (una lettura passata, tenuta come base per
     * calcolare la prossima soglia), mentre due_date è la data FUTURA in
     * cui la prossima scadenza è prevista: abbinarli produrrebbe una
     * lettura falsa (il km del cambio precedente, spacciato per quello di
     * un anno dopo). Per quei due tipi, l'unica data affidabile per un km
     * noto è quella dell'appuntamento che l'ha registrato — già gestita
     * direttamente da MaintenanceRecordController.
     */
    private const MILEAGE_DATE_MATCHES_DUE_DATE_TYPES = [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN];

    /**
     * Crea una nuova scadenza con calcolo automatico della data.
     *
     * Per i tipi periodici (Revisione Ministeriale/Impianto Ossigeno), se il
     * veicolo ha già una scadenza dello stesso tipo non ancora rinnovata,
     * viene marcata automaticamente come rinnovata e collegata alla nuova
     * (renews_deadline_id), così da poterla ripristinare se la nuova viene
     * eliminata per errore.
     */
    public function createDeadline(array $data, Vehicle $vehicle): Deadline
    {
        DeadlineDueDateResolver::validateOxygenForVehicle($data, $vehicle);

        $dueDate = DeadlineDueDateResolver::resolve($data, $vehicle);

        if (! $dueDate) {
            throw new \RuntimeException('Impossibile calcolare automaticamente la data di scadenza: controlla immatricolazione e configurazione tipo veicolo.');
        }

        $previousDeadline = in_array($data['type'], self::AUTO_RENEW_TYPES, true)
            ? $this->findCurrentDeadlineToRenew($vehicle, $data['type'])
            : null;

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => $data['type'],
            'due_date' => $dueDate->toDateString(),
            'is_renewed' => (bool) ($data['is_renewed'] ?? false),
            'renews_deadline_id' => $previousDeadline?->id,
            'interval_km' => $data['interval_km'] ?? null,
            // Se stiamo rinnovando una scadenza precedente ($previousDeadline),
            // il km inserito nel form è quello della revisione appena
            // effettuata (quella precedente), non di questa — che
            // rappresenta la prossima, futura, e il cui km non è ancora
            // noto. Va sulla precedente, non qui.
            'last_mileage' => $previousDeadline ? null : ($data['last_mileage'] ?? null),
            'interval_days' => $data['interval_days'] ?? null,
            'insurance_company' => $data['insurance_company'] ?? null,
            'insurance_policy_number' => $data['insurance_policy_number'] ?? null,
            'insurance_premium' => $data['insurance_premium'] ?? null,
            'insurance_coverage_type' => $data['insurance_coverage_type'] ?? null,
            'insurance_coverage_limit' => $data['insurance_coverage_limit'] ?? null,
            'insurance_broker_contact' => $data['insurance_broker_contact'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $deadline->syncStatusFromRules();

        if ($previousDeadline) {
            $previousDeadline->update([
                'is_renewed' => true,
                'status' => Deadline::STATUS_RENEWED,
                'last_mileage' => $data['last_mileage'] ?? $previousDeadline->last_mileage,
            ]);
        }

        // La lettura km+data da registrare è quella della scadenza appena
        // effettuata: $previousDeadline se stiamo rinnovando, altrimenti
        // questa stessa (primo record del suo tipo per il veicolo).
        $mileageSource = $previousDeadline ?? $deadline;

        if (in_array($mileageSource->type, self::MILEAGE_DATE_MATCHES_DUE_DATE_TYPES, true)) {
            $this->mileageLogService->recordReading($vehicle, $mileageSource->due_date, $mileageSource->last_mileage);
        }

        return $deadline;
    }

    /**
     * Crea la scadenza iniziale della cinghia di distribuzione per un
     * veicolo, calcolata dalla sua data di immatricolazione (10 anni/
     * 100.000 km, il primo dei due). Stessa logica usata da VehicleObserver
     * alla creazione del veicolo (se già dotato di cinghia); esposta
     * qui perché serve anche quando il flag viene attivato in un secondo
     * momento, modificando un veicolo già esistente.
     */
    public function createInitialTimingBeltDeadline(Vehicle $vehicle): Deadline
    {
        $intervalDays = $vehicle->timingBeltIntervalDays();
        $dueDate = $intervalDays
            ? Carbon::parse($vehicle->immatricolation_date)->addDays($intervalDays)
            : null;

        return Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'due_date' => $dueDate?->toDateString(),
            'interval_km' => Deadline::TIMING_BELT_INTERVAL_KM,
            'last_mileage' => 0,
            'interval_days' => $intervalDays,
        ]);
    }

    /**
     * Crea la scadenza cinghia quando si completa un appuntamento "Cambio
     * Cinghia" ma il veicolo non ne aveva già una da rinnovare (es. perché
     * il tipo di distribuzione è stato impostato/corretto solo dopo la
     * creazione del veicolo, senza passare dal prompt "crea scadenza
     * cinghia"). Senza questo, il completamento non faceva nulla in
     * silenzio: il cambio veniva registrato ma nessuna scadenza nasceva
     * mai, lasciando il veicolo permanentemente senza tracciamento cinghia.
     * Stessa logica di createInitialTimingBeltDeadline(), ma basata sulla
     * data/km del cambio appena effettuato invece che sull'immatricolazione.
     */
    public function createTimingBeltDeadlineFromChange(Vehicle $vehicle, Carbon $changedDate, ?int $mileage): Deadline
    {
        $intervalDays = $vehicle->timingBeltIntervalDays();
        $dueDate = $intervalDays ? $changedDate->copy()->addDays($intervalDays) : null;

        return Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'status' => Deadline::STATUS_PENDING,
            'due_date' => $dueDate?->toDateString(),
            'interval_km' => Deadline::TIMING_BELT_INTERVAL_KM,
            'last_mileage' => $mileage ?? 0,
            'interval_days' => $intervalDays,
        ]);
    }

    /**
     * Trova la scadenza "attuale" (non ancora rinnovata) dello stesso tipo
     * per il veicolo, quella che una nuova scadenza andrebbe a sostituire.
     */
    private function findCurrentDeadlineToRenew(Vehicle $vehicle, string $type): ?Deadline
    {
        return $vehicle->deadlines()
            ->where('type', $type)
            ->where('is_renewed', false)
            ->orderByDesc('due_date')
            ->first();
    }

    /**
     * Aggiorna una scadenza esistente con ricalcolo della data.
     *
     * Se la scadenza viene marcata come rinnovata (is_renewed) e appartiene a
     * un tipo con rinnovo periodico (ministeriale/ossigeno/tagliando), crea
     * automaticamente la scadenza successiva. La data di rinnovo può essere
     * fornita (due_date) oppure calcolata in automatico.
     */
    public function updateDeadline(Deadline $deadline, array $data, Vehicle $vehicle): Deadline
    {
        DeadlineDueDateResolver::validateOxygenForVehicle($data, $vehicle);

        $isRenewed = (bool) ($data['is_renewed'] ?? false);
        // La scadenza successiva va creata solo al passaggio effettivo a
        // rinnovata, non ad ogni salvataggio: senza questo controllo,
        // riaprire in modifica una scadenza già rinnovata (es. per
        // aggiungere solo il km di riferimento) e salvare rieseguiva la
        // creazione della "prossima" scadenza, duplicandola.
        $wasRenewed = $deadline->is_renewed;

        // DeadlineDueDateResolver::resolve() dà sempre la precedenza a una
        // data esplicita, sia in caso di rinnovo che di semplice correzione
        // manuale; altrimenti calcola la data in automatico per i tipi periodici.
        $dueDate = DeadlineDueDateResolver::resolve($data, $vehicle, $deadline->id);

        if (! $dueDate) {
            throw new \RuntimeException('Impossibile calcolare automaticamente la data di scadenza: controlla immatricolazione e configurazione tipo veicolo.');
        }

        $updateData = [
            'vehicle_id' => $vehicle->id,
            'type' => $data['type'],
            'due_date' => $dueDate->toDateString(),
            'is_renewed' => $isRenewed,
            'interval_km' => $data['interval_km'] ?? null,
            'last_mileage' => $data['last_mileage'] ?? null,
            'interval_days' => $data['interval_days'] ?? null,
            'insurance_company' => $data['insurance_company'] ?? null,
            'insurance_policy_number' => $data['insurance_policy_number'] ?? null,
            'insurance_premium' => $data['insurance_premium'] ?? null,
            'insurance_coverage_type' => $data['insurance_coverage_type'] ?? null,
            'insurance_coverage_limit' => $data['insurance_coverage_limit'] ?? null,
            'insurance_broker_contact' => $data['insurance_broker_contact'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        // syncStatusFromRules() non tocca lo stato di una scadenza rinnovata
        // (esce subito se is_renewed): senza impostarlo esplicitamente qui,
        // una scadenza marcata rinnovata da questo form restava con lo
        // stato precedente (es. "pending") invece di "renewed", disallineata
        // dal flag is_renewed e invisibile alle query che cercano l'ultima
        // scadenza rinnovata (calculateMinisterialDueDateForVehicle e affini).
        if ($isRenewed) {
            $updateData['status'] = Deadline::STATUS_RENEWED;
        }

        $deadline->update($updateData);

        $deadline->syncStatusFromRules();

        // Crea automaticamente la scadenza successiva per i tipi periodici
        // solo al momento in cui la scadenza corrente viene marcata come
        // rinnovata per la prima volta (non ad ogni modifica successiva).
        $justRenewed = $isRenewed && ! $wasRenewed;

        if ($justRenewed && in_array($deadline->type, [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN, Deadline::TYPE_TAGLIANDO], true)) {
            $this->createNextDeadlineAfterRenewal($deadline, $vehicle);
        }

        if (in_array($deadline->type, self::MILEAGE_DATE_MATCHES_DUE_DATE_TYPES, true)) {
            $this->mileageLogService->recordReading($vehicle, $deadline->due_date, $deadline->last_mileage);
        }

        return $deadline;
    }

    /**
     * Crea la scadenza successiva dopo un rinnovo, se non esiste già.
     *
     * Calcola la prossima data direttamente dalla data della scadenza appena
     * rinnovata (che è per definizione l'ultima), invece di ricercarla tra le
     * scadenze con status "renewed" escludendo quella corrente: quella
     * ricerca, pensata per calcolare la data di UNA scadenza a partire dalle
     * altre già rinnovate, escludeva qui proprio la scadenza da cui si
     * doveva partire, facendo ripiegare il calcolo sulla data di
     * immatricolazione (o su un rinnovo ancora precedente) invece che su
     * quella appena effettuata.
     */
    private function createNextDeadlineAfterRenewal(Deadline $renewedDeadline, Vehicle $vehicle): void
    {
        if (! $renewedDeadline->due_date) {
            return;
        }

        $nextDueDate = match ($renewedDeadline->type) {
            Deadline::TYPE_MINISTERIAL => $vehicle->vehicleType
                ? $renewedDeadline->due_date->copy()->addMonthsNoOverflow((int) $vehicle->vehicleType->regular_inspection_months)
                : null,
            Deadline::TYPE_OXYGEN => $renewedDeadline->due_date->copy()->addMonthsNoOverflow(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS),
            Deadline::TYPE_TAGLIANDO => $renewedDeadline->due_date->copy()->addMonthsNoOverflow(Deadline::TAGLIANDO_INTERVAL_MONTHS),
            default => null,
        };

        if (! $nextDueDate) {
            return;
        }

        $this->createNextOccurrence($renewedDeadline, $vehicle, $nextDueDate);
    }

    /**
     * Crea la scadenza che rinnova $renewedDeadline, con la stessa guardia
     * anti-duplicati usata dal rinnovo via form di modifica (vedi sopra).
     *
     * Pubblico e con data esplicita perché non tutti i rinnovi calcolano la
     * prossima data allo stesso modo: il completamento di un appuntamento in
     * officina (MaintenanceRecordController) la calcola dalla data di
     * RIENTRO effettiva, non da quella originariamente pianificata su
     * $renewedDeadline. Centralizzare qui la guardia (invece di lasciare che
     * ogni chiamante reimplementi il proprio controllo) è ciò che è mancato
     * quando quel controller aveva una propria logica di rinnovo separata:
     * senza renews_deadline_id impostato in modo uniforme, la guardia non
     * poteva riconoscere una scadenza già creata da lì.
     *
     * $extra permette di impostare altri campi sulla nuova scadenza (es.
     * last_mileage/interval_km per tagliando e cinghia) nella stessa
     * chiamata, senza un update separato.
     */
    public function createNextOccurrence(Deadline $renewedDeadline, Vehicle $vehicle, ?Carbon $nextDueDate, array $extra = []): ?Deadline
    {
        return DeadlineOccurrenceCreator::create($renewedDeadline, $vehicle, $nextDueDate, $extra);
    }

    /**
     * Tipi periodici per cui esiste una logica di calcolo della prossima
     * occorrenza dopo un rinnovo (vedi renewWithoutAppointment()).
     * "Assicurazione" ha un intervallo fisso di un anno (vedi
     * INSURANCE_INTERVAL_MONTHS) ma solo per questo rinnovo manuale: il
     * completamento di un intervento in officina
     * (MaintenanceRecordController::renewDeadline()) non la include, dato
     * che una polizza non si rinnova con un appuntamento.
     */
    public const RENEWABLE_TYPES = [
        Deadline::TYPE_MINISTERIAL,
        Deadline::TYPE_OXYGEN,
        Deadline::TYPE_TAGLIANDO,
        Deadline::TYPE_CINGHIA,
        Deadline::TYPE_ASSICURAZIONE,
    ];

    /**
     * Rinnova una scadenza periodica SENZA un appuntamento in officina: un
     * veicolo acquistato usato può avere l'ultima revisione/tagliando/
     * cinghia già effettuati dal precedente proprietario, prima che questo
     * sistema iniziasse a tracciare il veicolo — non c'è un intervento da
     * registrare qui dentro, solo la data (e opzionalmente il km) in cui è
     * realmente avvenuto.
     *
     * Stessa logica di calcolo della prossima occorrenza di
     * MaintenanceRecordController::renewDeadline(), con $renewedDate come
     * base al posto della data di rientro di un appuntamento.
     */
    public function renewWithoutAppointment(Deadline $deadline, Vehicle $vehicle, Carbon $renewedDate, ?int $mileage = null): Deadline
    {
        return $this->manualRenewalService->renew($deadline, $vehicle, $renewedDate, $mileage);
    }
}
