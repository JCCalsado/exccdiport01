<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Option A: Clear specific tables before seeding
        \DB::table('subjects')->delete();
        \DB::table('fees')->delete();
        \DB::table('student_assessments')->delete();

        // User::factory(10)->create();

        $this->call([
            UserSeeder::class,
            FeeSeeder::class,
            SubjectSeeder::class,
            StudentAssessmentSeeder::class,
            PayablesSeeder::class,
            TransactionSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}