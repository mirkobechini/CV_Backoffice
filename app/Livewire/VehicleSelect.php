<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Brand;
use App\Models\CarModel;

class VehicleSelect extends Component
{
    public $brand_id;
    public $car_model_id;
    public $models = [];

    public function updatedBrandId($value)
    {
        $this->models = $value
            ? CarModel::where('brand_id', $value)->orderBy('name')->get()
            : [];
        $this->car_model_id = null;
    }

    public function mount($brand_id = null, $car_model_id = null)
    {
        $this->brand_id = $brand_id;
        $this->car_model_id = $car_model_id;

        $this->models = $brand_id
            ? CarModel::where('brand_id', $brand_id)->orderBy('name')->get()
            : [];
    }

    public function render()
    {
        return view('livewire.vehicle-select', [
            'brands' => Brand::orderBy('name')->get()
        ]);
    }
}
