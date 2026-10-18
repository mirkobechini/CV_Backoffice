<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Card "Scadenze" della scheda veicolo: l'assicurazione deve seguire lo
 * stesso filtro pending/expired delle altre scadenze (bug segnalato
 * 2026-10-18: compariva sempre, anche lontana nel tempo, insieme al
 * messaggio "Nessuna scadenza attiva" che la contraddiceva).
 */
class VehicleShowDeadlinesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function createVehicle(): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'vehicle_type_id' => $vehicleType->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
    }

    public function test_insurance_far_in_the_future_is_not_shown_as_an_active_deadline(): void
    {
        // "Stato Scadenze" e lo storico mostrano sempre l'assicurazione (2
        // occorrenze della data): il segnale per la card "Scadenze" (attive)
        // è che non ne aggiunga una terza.
        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $dueDate = Carbon::today()->addYears(2);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_ASSICURAZIONE,
            'due_date' => $dueDate,
        ]);

        $response = $this->actingAs($user)->get(route('admin.vehicles.show', $vehicle));

        $response->assertOk();
        $response->assertSee(__('Nessuna scadenza attiva'));
        $this->assertSame(2, substr_count($response->getContent(), $dueDate->format('d/m/Y')));
    }

    public function test_expired_insurance_is_shown_as_an_active_deadline(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $dueDate = Carbon::yesterday();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_ASSICURAZIONE,
            'due_date' => $dueDate,
        ]);

        $response = $this->actingAs($user)->get(route('admin.vehicles.show', $vehicle));

        $response->assertOk();
        $response->assertDontSee(__('Nessuna scadenza attiva'));
        $this->assertSame(3, substr_count($response->getContent(), $dueDate->format('d/m/Y')));
    }
}
