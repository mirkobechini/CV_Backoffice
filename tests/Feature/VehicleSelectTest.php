<?php

namespace Tests\Feature;

use App\Livewire\VehicleSelect;
use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleSelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_mount_loads_models_for_selected_brand(): void
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);

        $component = Livewire::test(VehicleSelect::class, ['brand_id' => $brand->id]);
        $component->assertSet('brand_id', $brand->id);
        $this->assertCount(1, $component->get('models'));
        $this->assertEquals($model->id, $component->get('models')->first()->id);
    }

    public function test_updated_brand_id_loads_models_and_resets_car_model(): void
    {
        $brand1 = Brand::create(['name' => 'Fiat']);
        $brand2 = Brand::create(['name' => 'Ford']);
        CarModel::create(['name' => 'Ducato', 'brand_id' => $brand1->id]);
        $fordModel = CarModel::create(['name' => 'Focus', 'brand_id' => $brand2->id]);

        $component = Livewire::test(VehicleSelect::class)
            ->set('brand_id', $brand2->id);
        $component->assertSet('car_model_id', null);
        $this->assertCount(1, $component->get('models'));
        $this->assertEquals($fordModel->id, $component->get('models')->first()->id);
    }

    public function test_updated_brand_id_with_null_clears_models(): void
    {
        Livewire::test(VehicleSelect::class)
            ->set('brand_id', null)
            ->assertSet('models', []);
    }
}
