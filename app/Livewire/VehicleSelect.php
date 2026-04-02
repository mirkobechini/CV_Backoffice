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

    public function render()
    {
        return view('livewire.vehicle-select', [
            'brands' => Brand::orderBy('name')->get()
        ]);
    }
}