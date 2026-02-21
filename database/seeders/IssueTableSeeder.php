<?php

namespace Database\Seeders;

use App\Models\Issue;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IssueTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $issues = [
            [
                'vehicle_id' => 4,
                'description' => 'Segnala freni',
                'status' => 'in_progress',
                'event_date' => '2026-02-18'
            ],
            [
                'vehicle_id' => 4,
                'description' => 'Spazzola tergicristallo da cambiare',
                'status' => 'closed',
                'event_date' => '2026-02-18'
            ],
            [
                'vehicle_id' => 4,
                'description' => 'Passaruota anteriore sinistro (sara)',
                'status' => 'open',
                'event_date' => '2025-08-28'
            ],
            [
                'vehicle_id' => 1,
                'description' => 'Stop sinistro (andreas)',
                'status' => 'open',
                'event_date' => '2025-11-11'
            ],
        ];

        foreach ($issues as $issue) {
            Issue::create($issue);
        }
    }
}
