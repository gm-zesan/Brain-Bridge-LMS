<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TeacherLevelSeeder::class,
            UserSeeder::class,

            SubjectSeeder::class,
            SkillSeeder::class,

            // CourseSeeder::class,
            // ModuleSeeder::class,
            // VideoLessonSeeder::class,
            // AvailableSlotSeeder::class,
        ]);
    }
}
