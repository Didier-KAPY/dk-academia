<?php

namespace Database\Seeders;

use App\Models\DeliveryMode;
use Illuminate\Database\Seeder;

class DeliveryModeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            'en-ligne' => 'En ligne',
            'presentiel' => 'En présentiel',
            'a-domicile' => 'À domicile',
        ] as $slug => $name) {
            DeliveryMode::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_active' => true],
            );
        }
    }
}
