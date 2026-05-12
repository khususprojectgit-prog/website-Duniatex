<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\DefectType;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---------------------------------------------------------------
        // Users: one per role
        // ---------------------------------------------------------------
        $admin = User::updateOrCreate(
            ['email' => 'admin@duniatex.com'],
            [
                'name'     => 'Admin Duniatex',
                'password' => Hash::make('password'),
                'role'     => 'admin',
                'status'   => 'active',
            ]
        );

        $qc = User::updateOrCreate(
            ['email' => 'qc@duniatex.com'],
            [
                'name'     => 'QC Supervisor',
                'password' => Hash::make('password'),
                'role'     => 'qc',
                'status'   => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'operator@duniatex.com'],
            [
                'name'     => 'Operator Lantai',
                'password' => Hash::make('password'),
                'role'     => 'operator',
                'status'   => 'active',
            ]
        );

        // ---------------------------------------------------------------
        // Defect types (standard 4-Point System categories)
        // ---------------------------------------------------------------
        $defectTypes = [
            ['defect_name' => 'Hole',              'default_point' => 4, 'description' => 'Hole in fabric — any size.'],
            ['defect_name' => 'Broken End',        'default_point' => 4, 'description' => 'Missing warp yarn causing a line in the fabric.'],
            ['defect_name' => 'Broken Pick',       'default_point' => 2, 'description' => 'Missing weft yarn causing a horizontal stripe.'],
            ['defect_name' => 'Knot',              'default_point' => 1, 'description' => 'Visible knot on fabric surface.'],
            ['defect_name' => 'Oil Stain',         'default_point' => 3, 'description' => 'Oil or grease mark on fabric.'],
            ['defect_name' => 'Weaving Bar',       'default_point' => 2, 'description' => 'Horizontal banding visible across fabric width.'],
            ['defect_name' => 'Reed Mark',         'default_point' => 1, 'description' => 'Vertical stripe caused by defective reed wire.'],
            ['defect_name' => 'Slub',              'default_point' => 1, 'description' => 'Thick place in yarn.'],
            ['defect_name' => 'Tight End',         'default_point' => 2, 'description' => 'Overly tight warp yarn causing puckering.'],
            ['defect_name' => 'Contamination',     'default_point' => 3, 'description' => 'Foreign fibre contamination in fabric.'],
        ];

        foreach ($defectTypes as $dt) {
            DefectType::updateOrCreate(['defect_name' => $dt['defect_name']], $dt);
        }

        // ---------------------------------------------------------------
        // Machines
        // ---------------------------------------------------------------
        $machines = [
            ['machine_name' => 'Loom A-01', 'machine_type' => 'Rapier Loom',   'location' => 'Hall A'],
            ['machine_name' => 'Loom A-02', 'machine_type' => 'Rapier Loom',   'location' => 'Hall A'],
            ['machine_name' => 'Loom B-01', 'machine_type' => 'Air Jet Loom',  'location' => 'Hall B'],
            ['machine_name' => 'Loom B-02', 'machine_type' => 'Air Jet Loom',  'location' => 'Hall B'],
            ['machine_name' => 'Knit C-01', 'machine_type' => 'Circular Knit', 'location' => 'Hall C'],
        ];

        foreach ($machines as $m) {
            Machine::updateOrCreate(['machine_name' => $m['machine_name']], $m);
        }

        // ---------------------------------------------------------------
        // Clients
        // ---------------------------------------------------------------
        $clients = [
            [
                'client_name'    => 'Budi Tekstil',
                'company'        => 'PT Budi Tekstil Makmur',
                'contact_person' => 'Budi Santoso',
                'phone'          => '081234567890',
                'address'        => 'Jl. Industri No. 10, Jakarta',
            ],
            [
                'client_name'    => 'Sari Garment',
                'company'        => 'CV Sari Garment Indonesia',
                'contact_person' => 'Sari Dewi',
                'phone'          => '082345678901',
                'address'        => 'Jl. Maju Jaya No. 5, Bandung',
            ],
            [
                'client_name'    => 'Nusantara Fabrics',
                'company'        => 'PT Nusantara Fabrics',
                'contact_person' => 'Ahmad Fauzi',
                'phone'          => '083456789012',
                'address'        => 'Jl. Ekspor No. 99, Surabaya',
            ],
        ];

        foreach ($clients as $c) {
            Client::updateOrCreate(['client_name' => $c['client_name']], $c);
        }

        $this->command->info('✅  Duniatex seed complete.');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin',    'admin@duniatex.com',    'password'],
                ['QC',       'qc@duniatex.com',       'password'],
                ['Operator', 'operator@duniatex.com', 'password'],
            ]
        );
    }
}
