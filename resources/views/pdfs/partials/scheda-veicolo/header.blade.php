<!-- INTESTAZIONE -->
<div class="header">
    <div class="title">
        <h1>SCHEDA VEICOLO</h1>
    </div>
    <div class="subtitle">
        <span class="badge">{{ $vehicle->vehicleType->name }}</span>
        <p class="timestamp">Stampata il {{ date('d/m/Y H:i') }}</p>
    </div>
</div>

<!-- HERO -->
<div class="hero">
    <div class="code">{{ $vehicle->internal_code }}</div>
    <div class="plate">{{ $vehicle->license_plate }}</div>
</div>
