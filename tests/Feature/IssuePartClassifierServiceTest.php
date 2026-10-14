<?php

namespace Tests\Feature;

use App\Exceptions\IssuePartClassificationException;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\Issue;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\IssuePartClassifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IssuePartClassifierServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'test-key']);
    }

    private function vehicle(): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $group = Group::create(['name' => 'Gruppo test', 'invite_code' => Group::generateInviteCode()]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'vehicle_type_id' => $type->id,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);
    }

    private function fakeChatCompletion(array $content): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($content)]],
                ],
            ]),
        ]);
    }

    public function test_suggest_parts_returns_classification_keyed_by_issue_id(): void
    {
        $vehicle = $this->vehicle();
        $issue1 = Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Frena male', 'status' => 'open', 'event_date' => '2026-01-01']);
        $issue2 = Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Foratura ruota', 'status' => 'open', 'event_date' => '2026-01-02']);

        $this->fakeChatCompletion([
            'classifications' => [
                ['id' => $issue1->id, 'part' => Issue::PART_FRENI],
                ['id' => $issue2->id, 'part' => Issue::PART_PNEUMATICI],
            ],
        ]);

        $suggestions = app(IssuePartClassifierService::class)->suggestParts(Issue::all());

        $this->assertSame([
            $issue1->id => Issue::PART_FRENI,
            $issue2->id => Issue::PART_PNEUMATICI,
        ], $suggestions);
    }

    public function test_suggest_parts_returns_null_for_part_outside_fixed_vocabulary(): void
    {
        // Il modello a volte non rispetta lo schema richiesto: una parte
        // inventata non deve mai finire silenziosamente nel suggerimento.
        $vehicle = $this->vehicle();
        $issue = Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Boh', 'status' => 'open', 'event_date' => '2026-01-01']);

        $this->fakeChatCompletion([
            'classifications' => [
                ['id' => $issue->id, 'part' => 'Parte inventata'],
            ],
        ]);

        $suggestions = app(IssuePartClassifierService::class)->suggestParts(Issue::all());

        $this->assertNull($suggestions[$issue->id]);
    }

    public function test_suggest_parts_batches_large_collections(): void
    {
        $vehicle = $this->vehicle();
        $issues = collect(range(1, 30))->map(fn ($n) => Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => "Guasto {$n}",
            'status' => 'open',
            'event_date' => '2026-01-01',
        ]));

        Http::fake(function ($request) {
            $body = json_decode($request->body(), true);
            $descriptions = json_decode($body['messages'][1]['content'], true);

            return Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'classifications' => collect($descriptions)->map(fn ($d) => ['id' => $d['id'], 'part' => Issue::PART_ALTRO])->all(),
                    ])]],
                ],
            ]);
        });

        $suggestions = app(IssuePartClassifierService::class)->suggestParts($issues);

        $this->assertCount(30, $suggestions);
        Http::assertSentCount(2); // 30 issues / batch size 25 = 2 chiamate
    }

    public function test_suggest_parts_returns_empty_array_for_empty_collection(): void
    {
        $suggestions = app(IssuePartClassifierService::class)->suggestParts(collect());

        $this->assertSame([], $suggestions);
        Http::assertNothingSent();
    }

    public function test_suggest_parts_throws_without_api_key(): void
    {
        config(['services.openrouter.key' => null]);
        $vehicle = $this->vehicle();
        $issue = Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Boh', 'status' => 'open', 'event_date' => '2026-01-01']);

        $this->expectException(IssuePartClassificationException::class);

        app(IssuePartClassifierService::class)->suggestParts(collect([$issue]));
    }

    public function test_suggest_parts_throws_on_malformed_response(): void
    {
        $vehicle = $this->vehicle();
        $issue = Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Boh', 'status' => 'open', 'event_date' => '2026-01-01']);

        Http::fake([
            '*/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'not json']]]]),
        ]);

        $this->expectException(IssuePartClassificationException::class);

        app(IssuePartClassifierService::class)->suggestParts(collect([$issue]));
    }
}
