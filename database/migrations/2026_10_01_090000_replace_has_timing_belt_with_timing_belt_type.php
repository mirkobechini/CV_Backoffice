<?php

use App\Models\Deadline;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * has_timing_belt era un booleano cinghia/catena, ma esistono in realtà
     * due tipi di cinghia con scadenze diverse: a secco (solo km) e a bagno
     * d'olio (km e tempo, il comportamento che il booleano true aveva finora).
     * I veicoli già segnati "true" diventano "cinghia a secco" (non "a bagno
     * d'olio"): per questo le loro scadenze cinghia esistenti perdono la
     * componente temporale (due_date/interval_days), restando solo a km.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('timing_belt_type')->nullable()->after('has_timing_belt');
        });

        DB::table('vehicles')->where('has_timing_belt', true)->update(['timing_belt_type' => 'dry_belt']);
        DB::table('vehicles')->where('has_timing_belt', false)->orWhereNull('has_timing_belt')->update(['timing_belt_type' => 'chain']);

        $dryBeltVehicleIds = DB::table('vehicles')->where('timing_belt_type', 'dry_belt')->pluck('id');

        DB::table('deadlines')
            ->where('type', Deadline::TYPE_CINGHIA)
            ->whereIn('vehicle_id', $dryBeltVehicleIds)
            ->update(['due_date' => null, 'interval_days' => null]);

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('has_timing_belt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->boolean('has_timing_belt')->default(false)->after('timing_belt_type');
        });

        DB::table('vehicles')->where('timing_belt_type', '!=', 'chain')->update(['has_timing_belt' => true]);
        DB::table('vehicles')->where('timing_belt_type', 'chain')->orWhereNull('timing_belt_type')->update(['has_timing_belt' => false]);

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('timing_belt_type');
        });
    }
};
