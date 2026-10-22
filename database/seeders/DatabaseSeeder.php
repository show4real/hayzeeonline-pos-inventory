<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// Laravel 8 moved seeders from database/seeds to database/seeders and gave them
// the Database\Seeders namespace, which is why composer.json no longer needs a
// classmap entry for this directory.
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Nothing to seed. The production database is the source of truth and
        // the deploy does not seed — see docker/deploy.sh.
    }
}
