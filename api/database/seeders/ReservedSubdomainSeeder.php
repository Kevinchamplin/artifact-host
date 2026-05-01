<?php

namespace Database\Seeders;

use App\Models\ReservedSubdomain;
use Illuminate\Database\Seeder;

class ReservedSubdomainSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'www', 'api', 'mcp', 'panel', 'admin', 'mail', 'ftp',
            'app', 'docs', 'blog', 'status', 'billing', 'dashboard',
        ];

        foreach ($names as $name) {
            ReservedSubdomain::query()->updateOrCreate(['subdomain' => $name]);
        }
    }
}
