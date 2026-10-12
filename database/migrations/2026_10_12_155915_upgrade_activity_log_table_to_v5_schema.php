<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migrazione da spatie/laravel-activitylog v4 a v5 (vedi vendor/spatie/
 * laravel-activitylog/UPGRADING.md "From v4 to v5"): la colonna
 * "properties" smette di contenere anche le modifiche tracciate del
 * modello (attributes/old), che il pacchetto ora legge/scrive in una
 * colonna dedicata "attribute_changes". Senza migrare i dati esistenti,
 * lo storico "prima/dopo" già accumulato nell'activity log sarebbe letto
 * da ActivityLogController nel posto sbagliato e apparirebbe vuoto.
 *
 * batch_uuid era già inutilizzata nel nostro codice (il sistema di batch
 * di v4 non è mai stato adottato): rimossa come da guida ufficiale, dato
 * che il pacchetto l'ha dismessa del tutto in v5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->json('attribute_changes')->nullable()->after('causer_id');
        });

        DB::table('activity_log')
            ->where(function ($query) {
                $query->whereNotNull('properties->attributes')
                    ->orWhereNotNull('properties->old');
            })
            ->orderBy('id')
            ->each(function ($row) {
                $properties = json_decode($row->properties, true) ?? [];
                $changes = array_intersect_key($properties, array_flip(['attributes', 'old']));
                $remaining = array_diff_key($properties, array_flip(['attributes', 'old']));

                DB::table('activity_log')->where('id', $row->id)->update([
                    'attribute_changes' => empty($changes) ? null : json_encode($changes),
                    'properties' => empty($remaining) ? null : json_encode($remaining),
                ]);
            });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn('batch_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->uuid('batch_uuid')->nullable()->after('properties');
        });

        DB::table('activity_log')
            ->whereNotNull('attribute_changes')
            ->orderBy('id')
            ->each(function ($row) {
                $changes = json_decode($row->attribute_changes, true) ?? [];
                $properties = json_decode($row->properties, true) ?? [];
                $merged = array_merge($properties, $changes);

                DB::table('activity_log')->where('id', $row->id)->update([
                    'properties' => empty($merged) ? null : json_encode($merged),
                ]);
            });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn('attribute_changes');
        });
    }
};
