<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Assegna tutti gli utenti esistenti al gruppo di default.
        // Gli admin diventano capi, gli altri membri.
        $defaultGroupId = DB::table('groups')->where('name', 'Associazione di default')->value('id');

        if ($defaultGroupId) {
            $users = DB::table('users')->get(['id', 'role']);

            foreach ($users as $user) {
                $role = $user->role === 'admin' ? 'capo' : 'member';
                DB::table('group_user')->insert([
                    'group_id' => $defaultGroupId,
                    'user_id' => $user->id,
                    'role' => $role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Rimuove la colonna role da users (il ruolo ora vive nel pivot group_user).
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('worker')->after('email');
        });

        // Ripristina il ruolo dall'ultimo gruppo a cui l'utente appartiene.
        $memberships = DB::table('group_user')->get(['user_id', 'role']);

        foreach ($memberships as $membership) {
            $role = $membership->role === 'capo' ? 'admin' : 'worker';
            DB::table('users')->where('id', $membership->user_id)->update(['role' => $role]);
        }
    }
};
