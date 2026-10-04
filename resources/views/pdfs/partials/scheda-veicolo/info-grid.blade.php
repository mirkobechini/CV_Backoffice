<!-- INFO GRID -->
<div class="info-grid">
    <div class="info-card">
        <h3>Anagrafica</h3>
        <div class="info-row">
            <span class="label">Marca</span>
            <span class="value">{{ $vehicle->brand->name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Modello</span>
            <span class="value">{{ $vehicle->carModel->name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Carburante</span>
            <span class="value">{{ $vehicle->fuel_type }}</span>
        </div>
        <div class="info-row">
            <span class="label">Immatricolazione</span>
            <span class="value">{{ $vehicle->immatricolation_date?->format('d/m/Y') ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Chilometri</span>
            <span class="value">{{ number_format($vehicle->mileage ?? 0, 0, ',', '.') }} km</span>
        </div>
    </div>

    <div class="info-card">
        <h3>Documenti & Garanzia</h3>
        <div class="info-row">
            <span class="label">Carta circolazione</span>
            <span class="value">{{ $vehicle->registration_card_path ? 'Disponibile' : 'Non Disponibile' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Garanzia</span>
            <span
                class="value">{{ $vehicle->warranty_expiration_date && $vehicle->is_warranty_expired ? 'Scaduta (' . $vehicle->warranty_expiration_date->format('d/m/Y') . ')' : 'Valida' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Estensione</span>
            <span
                class="value">{{ $vehicle->warranty_extension_duration ? $vehicle->warranty_extension_duration . ' mesi' : 'Nessuna' }}</span>
        </div>
    </div>

    <div class="info-card">
        <h3>Specifiche tecniche</h3>
        <div class="info-row">
            <span class="label">Telaio (VIN)</span>
            <span class="value">{{ $vehicle->vin ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Colore</span>
            <span class="value">{{ $vehicle->color ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Numero posti</span>
            <span class="value">{{ $vehicle->seats ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Categoria veicolo</span>
            <span class="value">{{ $vehicle->vehicle_category ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Classe ambientale</span>
            <span class="value">{{ $vehicle->environmental_class ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Massa massima</span>
            <span
                class="value">{{ $vehicle->max_mass_kg ? number_format($vehicle->max_mass_kg, 0, ',', '.') . ' kg' : 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Cilindrata</span>
            <span
                class="value">{{ $vehicle->engine_displacement_cc ? number_format($vehicle->engine_displacement_cc, 0, ',', '.') . ' cc' : 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Potenza</span>
            <span class="value">{{ $vehicle->engine_power_kw ? $vehicle->engine_power_kw . ' kW' : 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Misure pneumatici</span>
            <span
                class="value">{{ ! empty($vehicle->allowed_tire_sizes) ? implode(', ', $vehicle->allowed_tire_sizes) : 'N/A' }}</span>
        </div>
    </div>

</div>
