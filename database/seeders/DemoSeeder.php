<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Skill;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data so every screen has something to show.
 * Logins (password for all: password):
 *   Super admin  admin@lwe.test
 *   Mentor (VIP) mentor@lwe.test
 *   Student      03001234567
 */
class DemoSeeder extends Seeder
{
    public function run(EnrollmentService $enrollments): void
    {
        // Demo data must not send real emails / WhatsApp messages.
        \Illuminate\Support\Facades\Notification::fake();

        $admin = $this->user('Super Admin', 'admin@lwe.test', '923000000001', User::ROLE_SUPER_ADMIN);
        $mentorVip = $this->user('Demo VIP Mentor', 'mentor@lwe.test', '923000000002', User::ROLE_MENTOR);
        $mentor = $this->user('Demo Mentor', 'mentor2@lwe.test', '923000000003', User::ROLE_MENTOR);

        $ecom = $this->course('E-commerce Mastery', 45000, true, $mentorVip, ['Product Research', 'Shopify Store Setup'], [
            'Week 1: Foundations' => ['Welcome and roadmap' => 'reading', 'How online selling works' => 'recorded_video', 'Live: Q&A kickoff' => 'live_class'],
            'Week 2: Products' => ['Winning product research' => 'live_class', 'Supplier hunting' => 'recorded_video', 'Task: find 3 products' => 'task'],
        ], $admin);

        $fiverr = $this->course('Freelancing with Fiverr', null, false, $mentor, ['Fiverr Gig SEO'], [
            'Getting started' => ['Create your seller account' => 'recorded_video', 'Gig SEO basics' => 'reading'],
        ], $admin);

        $canva = $this->course('Canva for Business', 15000, false, $mentor, ['Canva Design'], [
            'Design basics' => ['Layouts and fonts' => 'recorded_video', 'Brand kit' => 'reading'],
        ], $admin);

        $b1 = Batch::create([
            'course_id' => $ecom->id, 'name' => 'Batch 14', 'mentor_id' => $mentorVip->id,
            'starts_on' => now()->subWeeks(2)->toDateString(), 'ends_on' => now()->addWeeks(6)->toDateString(),
            'class_days' => ['mon', 'wed', 'fri'], 'class_time' => '20:00', 'class_duration_minutes' => 90,
            'seats' => 300, 'status' => 'running',
        ]);
        // Batch 14 teaches two courses (a batch can hold several).
        $b1->setCourses([$ecom->id, $canva->id]);

        $b2 = Batch::create([
            'course_id' => $fiverr->id, 'name' => 'Free Batch 3', 'mentor_id' => $mentor->id,
            'starts_on' => now()->addDays(3)->toDateString(),
            'class_days' => ['tue', 'sat'], 'class_time' => '21:00', 'class_duration_minutes' => 60,
            'status' => 'upcoming',
        ]);

        Coupon::create(['code' => 'SCHOLAR50', 'type' => 'percent', 'value' => 50, 'max_uses' => 100, 'is_active' => true, 'note' => 'Scholarship session']);

        // 20 demo students
        $first = $this->user('Ali Khan', 'ali@lwe.test', '923001234567', User::ROLE_STUDENT);
        $enrollments->enroll($first, $ecom, $b1, null, 'admin', $admin, true);
        $enrollments->enroll($first, $fiverr, $b2);

        foreach (range(1, 19) as $i) {
            $s = $this->user("Demo Student {$i}", null, '92300'.str_pad((string) (2000000 + $i), 7, '0', STR_PAD_LEFT), User::ROLE_STUDENT);
            $i % 3 === 0
                ? $enrollments->enroll($s, $ecom, $b1, 'SCHOLAR50')           // pending payment, 50% off
                : $enrollments->enroll($s, $ecom, $b1, null, 'scholarship', $admin, true);
        }

        // ---- Module 3 demo: a task, a quiz and a Q&A question ----
        $research = $ecom->lessons()->where('title', 'Winning product research')->first();
        \App\Models\Task::create([
            'course_id' => $ecom->id, 'lesson_id' => $research?->id, 'created_by' => $mentorVip->id,
            'title' => 'Find 3 winning products',
            'instructions' => "1. Use the method from the class to find 3 products.\n2. For each product write: name, selling price, supplier price, why it can sell.\n3. Submit as text, or a screenshot of your sheet.",
            'rubric' => 'Full marks: 3 products with real prices and a clear reason. Lose marks: missing prices or no reason.',
            'submission_type' => 'any', 'max_marks' => 10, 'due_at' => now()->addDays(4), 'status' => 'published', 'published_at' => now(),
        ]);

        $quiz = \App\Models\Quiz::create([
            'course_id' => $ecom->id, 'title' => 'Week 1 quiz: online selling basics', 'created_by' => $mentorVip->id,
            'time_limit_minutes' => 10, 'pass_percent' => 60, 'max_attempts' => 2, 'status' => 'published', 'published_at' => now(),
        ]);
        foreach ([
            ['single', 'Dropshipping mein product kaun ship karta hai?', ['Seller khud', 'Supplier', 'Customer', 'Courier company'], [1]],
            ['true_false', 'Winning product ke liye sirf sasta hona kaafi hai.', ['True', 'False'], [1]],
            ['multiple', 'In mein se kaun se product research tools hain?', ['Google Trends', 'TikTok search', 'MS Paint', 'AliExpress best sellers'], [0, 1, 3]],
        ] as $pos => [$type, $q, $options, $correct]) {
            $quiz->questions()->create(['type' => $type, 'question' => $q, 'options' => $options, 'correct' => $correct, 'marks' => 1, 'position' => $pos]);
        }

        // ---- Module 4 demo: a published mentor page (replace with real details) ----
        \App\Models\MentorProfile::create([
            'user_id' => $mentorVip->id, 'slug' => 'demo-vip-mentor', 'is_published' => true,
            'headline' => 'E-commerce mentor — Amazon & Shopify (demo page, edit me)',
            'bio' => "This is a demo mentor page. Open Mentors → Edit page, write a few real facts, and press \"Write the page\" — AI writes the bio from your facts.",
            'expertise' => ['Amazon FBA', 'Shopify', 'Product Research'],
            'highlights' => [],
            'success_stories' => [],
        ]);
        $first->passportCode();

        \App\Models\QaQuestion::create([
            'course_id' => $ecom->id, 'user_id' => $first->id, 'title' => 'Shopify trial ke baad payment kaise add karein?',
            'body' => 'Trial khatam hone wala hai, Pakistan se card ke baghair kya option hai?', 'last_activity_at' => now(),
        ]);
    }

    private function user(string $name, ?string $email, string $phone, string $role): User
    {
        $user = User::firstOrCreate(['phone' => $phone], [
            'name' => $name, 'email' => $email, 'password' => Hash::make('password'), 'city' => 'Multan',
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function course(string $title, ?int $fee, bool $vip, User $mentor, array $skills, array $curriculum, User $by): Course
    {
        $course = Course::create([
            'title' => $title,
            'short_description' => "Practical {$title} course by Learning with Earning.",
            'description' => "Demo description for {$title}. Replace this with the real course details.",
            'what_you_learn' => ['Practical, step-by-step skills', 'Real tasks checked by your mentor', 'A verified Skill Passport'],
            'requirements' => ['A laptop or smartphone', 'Internet connection'],
            'category' => 'Digital Skills',
            'level' => 'beginner',
            'is_vip' => $vip,
            'fee' => $fee,
            'status' => 'published',
            'wizard_step' => 6,
            'created_by' => $by->id,
        ]);
        $course->mentors()->attach($mentor);
        $course->skills()->sync(collect($skills)->map(fn ($n) => Skill::firstOrCreate(['name' => $n])->id));

        $mPos = 0;
        foreach ($curriculum as $moduleTitle => $lessons) {
            $module = $course->modules()->create(['title' => $moduleTitle, 'position' => $mPos++]);
            $lPos = 0;
            foreach ($lessons as $lessonTitle => $type) {
                $module->lessons()->create([
                    'course_id' => $course->id,
                    'title' => $lessonTitle,
                    'type' => $type,
                    'summary' => 'Demo lesson summary.',
                    'body' => 'Demo lesson content. Replace with real notes.',
                    'position' => $lPos++,
                    'duration_minutes' => 20,
                    'is_preview' => $mPos === 1 && $lPos === 1,
                ]);
            }
        }

        return $course;
    }
}
