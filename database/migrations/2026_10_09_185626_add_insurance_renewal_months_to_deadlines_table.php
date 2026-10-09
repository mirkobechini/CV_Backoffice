<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            // Null = usa il default fisso (Deadline::INSURANCE_INTERVAL_MONTHS,
            // 12 mesi): permette polizze semestrali/pluriennali senza
            // forzare tutti a un anno nel rinnovo "senza appuntamento".
            $table->unsignedSmallInteger('insurance_renewal_months')->nullable()->after('insurance_broker_contact');
        });
    }

    public function down(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            $table->dropColumn('insurance_renewal_months');
        });
    }
};
