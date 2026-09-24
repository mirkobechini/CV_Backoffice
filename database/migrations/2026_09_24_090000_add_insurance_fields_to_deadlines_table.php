<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campi specifici per il tipo "Assicurazione" (mostrati solo per quel
     * tipo, come le colonne specifiche per categoria già usate su
     * equipment). "notes" resta generico e non prefissato per coerenza con
     * gli altri modelli, anche se per ora si mostra solo qui.
     */
    public function up(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            $table->string('insurance_company')->nullable()->after('type');
            $table->string('insurance_policy_number')->nullable()->after('insurance_company');
            $table->decimal('insurance_premium', 10, 2)->nullable()->after('insurance_policy_number');
            $table->string('insurance_coverage_type')->nullable()->after('insurance_premium');
            $table->decimal('insurance_coverage_limit', 12, 2)->nullable()->after('insurance_coverage_type');
            $table->string('insurance_broker_contact')->nullable()->after('insurance_coverage_limit');
            $table->text('notes')->nullable()->after('insurance_broker_contact');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            $table->dropColumn([
                'insurance_company',
                'insurance_policy_number',
                'insurance_premium',
                'insurance_coverage_type',
                'insurance_coverage_limit',
                'insurance_broker_contact',
                'notes',
            ]);
        });
    }
};
