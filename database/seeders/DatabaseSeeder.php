<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // create 3 subscription plans: monthly, quarterly, annual
        $plans = collect([
            SubscriptionPlan::factory()->monthly()->create(),
            SubscriptionPlan::factory()->quarterly()->create(),
            SubscriptionPlan::factory()->annual()->create(),
        ]);

        // create 8 instructors
        $instructors = User::factory()->instructor()->count(8)->create();

        // create 3 courses for each instructor = 24 courses
        $courses = $instructors->flatMap(
            fn (User $instructor) => Course::factory()->count(3)->create(['instructor_id' => $instructor->id])
        );

        $subscriptions = app(SubscriptionService::class);

        // create 50 students and assign them random subscription plans and courses
        User::factory()->student()->count(50)->create()->each(function (User $student) use ($plans, $courses, $subscriptions) {
            $plan = $plans->random(); // randomly select a subscription plan form the 3 available plans

            // randomly select 2 to 5 courses for the student to subscribe to بتختار رقم من 2 ل 5 وتعمل اساين ل الرقم ده مثلا لو 4 يبقي تعمل اساين ل 4 كورسات
            $courseIds = $courses->random(random_int(2, 5))->pluck('id');

            // purchase( Ahmed, Monthly, [1,2,3,4] )  purchase( student, plan, courseIds )
            $subscriptions->purchase($student, $plan, $courseIds);
        });

        $this->command->info('Seeded 8 instructors, 24 courses, 3 plans, 50 subscriptions with revenue allocated.');
    }
}
