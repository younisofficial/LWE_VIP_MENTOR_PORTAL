<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Manage;
use App\Http\Controllers\Mentor;
use App\Http\Controllers\QaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

// Zoom → portal events (signature-checked; CSRF is off for /webhooks/* in bootstrap/app.php)
Route::post('/webhooks/zoom', \App\Http\Controllers\Webhooks\ZoomWebhookController::class)->name('webhooks.zoom');

// ---------------- Public pages (no login) ----------------
Route::get('/sitemap.xml', [\App\Http\Controllers\PublicController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [\App\Http\Controllers\PublicController::class, 'robots'])->name('robots');
Route::get('/mentors', [\App\Http\Controllers\PublicController::class, 'mentors'])->name('mentors.index');
Route::get('/mentors/{slug}', [\App\Http\Controllers\PublicController::class, 'mentor'])->name('mentors.show');
Route::get('/passport/{code}', [\App\Http\Controllers\PublicController::class, 'passport'])->middleware('throttle:60,1')->name('passport.show');
Route::get('/verify/{code}', [\App\Http\Controllers\PublicController::class, 'certificate'])->middleware('throttle:60,1')->name('certificates.show');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', HomeController::class)->name('dashboard');

    // Profile (from Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Notification bell
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // ---------------- Student ----------------
    Route::name('student.')->group(function () {
        Route::get('/today', Student\TodayController::class)->name('today');

        Route::get('/courses', [Student\CatalogController::class, 'index'])->name('courses.index');
        Route::get('/courses/{course:slug}', [Student\CatalogController::class, 'show'])->name('courses.show');
        Route::post('/courses/{course:slug}/enroll', [Student\CatalogController::class, 'enroll'])
            ->middleware('throttle:10,1')->name('courses.enroll');
        Route::post('/courses/{course:slug}/coupon', [Student\CatalogController::class, 'couponPreview'])
            ->middleware('throttle:20,1')->name('courses.coupon');

        Route::get('/learn/{course:slug}', [Student\MyCourseController::class, 'show'])->name('my-courses.show');
        Route::get('/learn/{course:slug}/lessons/{lesson}', [Student\MyCourseController::class, 'lesson'])->name('lessons.show');
        Route::post('/learn/{course:slug}/lessons/{lesson}/complete', [Student\MyCourseController::class, 'complete'])->name('lessons.complete');
        Route::get('/learn/{course:slug}/lessons/{lesson}/attachment', [Student\MyCourseController::class, 'attachment'])->name('lessons.attachment');

        Route::get('/classes', [Student\ClassController::class, 'index'])->name('classes.index');
        Route::get('/classes/{session}/join', [Student\ClassController::class, 'join'])->name('classes.join');
        Route::get('/classes/heartbeat', [Student\ClassController::class, 'heartbeat'])->name('classes.heartbeat');
        Route::post('/classes/{session}/watch', [Student\ClassController::class, 'watch'])->middleware('throttle:10,1')->name('classes.watch');

        // Module 4 — my Skill Passport
        Route::get('/my-passport', [Student\PassportController::class, 'index'])->name('passport.index');
        Route::put('/my-passport', [Student\PassportController::class, 'update'])->name('passport.update');
        Route::post('/my-passport/ai', [Student\PassportController::class, 'ai'])->middleware('throttle:3,1')->name('passport.ai');

        // Module 3 — tasks, quizzes, daily attendance
        Route::get('/tasks', [Student\TaskController::class, 'index'])->name('tasks.index');
        Route::get('/tasks/{task}', [Student\TaskController::class, 'show'])->name('tasks.show');
        Route::post('/tasks/{task}', [Student\TaskController::class, 'submit'])->middleware('throttle:20,1')->name('tasks.submit');
        Route::get('/tasks/{task}/my-file', [Student\TaskController::class, 'myFile'])->name('tasks.my-file');

        Route::get('/quizzes', [Student\QuizController::class, 'index'])->name('quizzes.index');
        Route::post('/quizzes/{quiz}/start', [Student\QuizController::class, 'start'])->name('quizzes.start');
        Route::get('/quiz-attempts/{attempt}', [Student\QuizController::class, 'take'])->name('quizzes.take');
        Route::put('/quiz-attempts/{attempt}', [Student\QuizController::class, 'save'])->middleware('throttle:60,1')->name('quizzes.save');
        Route::post('/quiz-attempts/{attempt}', [Student\QuizController::class, 'submit'])->name('quizzes.submit');

        Route::get('/attendance', [Student\AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance', [Student\AttendanceController::class, 'store'])->middleware('throttle:10,1')->name('attendance.store');
        Route::get('/attendance/campus', [Student\AttendanceController::class, 'campus'])->middleware('throttle:20,1')->name('attendance.campus');

        Route::get('/payments/{enrollment}', [Student\PaymentController::class, 'create'])->name('payments.create');
        Route::post('/payments/{enrollment}', [Student\PaymentController::class, 'store'])
            ->middleware('throttle:5,1')->name('payments.store');
    });

    // ---------------- Mentor ----------------
    Route::middleware('role:'.User::ROLE_MENTOR)->prefix('mentor')->name('mentor.')->group(function () {
        Route::get('/', Mentor\DashboardController::class)->name('dashboard');
        Route::get('/classes/{session}', [Admin\ClassController::class, 'show'])->name('classes.show');
        Route::post('/classes/{session}/attendance/{user}', [Admin\ClassController::class, 'mark'])->name('classes.mark');
        Route::get('/classes/{session}/attendance.csv', [Admin\ClassController::class, 'export'])->name('classes.export');
        Route::post('/classes/{session}/notes/ai', [Admin\ClassController::class, 'generateNotes'])->middleware('throttle:10,1')->name('classes.notes.ai');
        Route::put('/classes/{session}/notes', [Admin\ClassController::class, 'saveNotes'])->name('classes.notes.save');
    });

    // Task attachment (students of the task + its staff)
    Route::get('/tasks/{task}/attachment', [Student\TaskController::class, 'attachment'])->name('tasks.attachment');

    // ---------------- Q&A board (students, mentors, admins — access checked per course) ----------------
    Route::prefix('qa')->name('qa.')->group(function () {
        Route::get('/', [QaController::class, 'index'])->name('index');
        Route::post('/', [QaController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::get('/{question}', [QaController::class, 'show'])->name('show');
        Route::delete('/{question}', [QaController::class, 'destroy'])->name('destroy');
        Route::post('/{question}/answers', [QaController::class, 'answer'])->middleware('throttle:20,1')->name('answer');
        Route::post('/{question}/moderate', [QaController::class, 'moderate'])->name('moderate');
        Route::post('/{question}/ai', [QaController::class, 'aiDraft'])->middleware('throttle:10,1')->name('ai');
        Route::get('/{question}/images/{index}', [QaController::class, 'image'])->whereNumber('index')->name('image');
        Route::post('/answers/{answer}/accept', [QaController::class, 'accept'])->name('accept');
        Route::delete('/answers/{answer}', [QaController::class, 'destroyAnswer'])->name('answers.destroy');
        Route::get('/answers/{answer}/images/{index}', [QaController::class, 'answerImage'])->whereNumber('index')->name('answer-image');
    });

    // ---------------- Tasks, quizzes, attendance — admins and mentors (mentors see only their courses) ----------------
    Route::middleware('role:'.User::ROLE_SUPER_ADMIN.'|'.User::ROLE_ADMIN.'|'.User::ROLE_MENTOR)->prefix('manage')->name('manage.')->group(function () {
        Route::get('tasks', [Manage\TaskController::class, 'index'])->name('tasks.index');
        Route::post('tasks', [Manage\TaskController::class, 'store'])->name('tasks.store');
        Route::post('tasks/ai', [Manage\TaskController::class, 'aiDraft'])->middleware('throttle:10,1')->name('tasks.ai');
        Route::get('tasks/{task}', [Manage\TaskController::class, 'show'])->name('tasks.show');
        Route::put('tasks/{task}', [Manage\TaskController::class, 'update'])->name('tasks.update');
        Route::delete('tasks/{task}', [Manage\TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::post('tasks/{task}/ai-check-all', [Manage\TaskController::class, 'aiReviewAll'])->middleware('throttle:5,1')->name('tasks.ai-all');
        Route::post('submissions/{submission}/grade', [Manage\TaskController::class, 'grade'])->name('submissions.grade');
        Route::post('submissions/{submission}/ai', [Manage\TaskController::class, 'aiReview'])->middleware('throttle:20,1')->name('submissions.ai');
        Route::get('submissions/{submission}/file', [Manage\TaskController::class, 'submissionFile'])->name('submissions.file');

        Route::get('quizzes', [Manage\QuizController::class, 'index'])->name('quizzes.index');
        Route::post('quizzes', [Manage\QuizController::class, 'store'])->name('quizzes.store');
        Route::get('quizzes/{quiz}/edit', [Manage\QuizController::class, 'edit'])->name('quizzes.edit');
        Route::put('quizzes/{quiz}', [Manage\QuizController::class, 'update'])->name('quizzes.update');
        Route::delete('quizzes/{quiz}', [Manage\QuizController::class, 'destroy'])->name('quizzes.destroy');
        Route::post('quizzes/{quiz}/ai', [Manage\QuizController::class, 'ai'])->middleware('throttle:10,1')->name('quizzes.ai');
        Route::get('quizzes/{quiz}/results', [Manage\QuizController::class, 'results'])->name('quizzes.results');
        Route::delete('quizzes/{quiz}/attempts/{userId}', [Manage\QuizController::class, 'resetAttempts'])->whereNumber('userId')->name('quizzes.reset');

        Route::get('attendance', [Manage\AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('attendance.csv', [Manage\AttendanceController::class, 'export'])->name('attendance.export');
        Route::get('attendance/campus-qr', [Manage\AttendanceController::class, 'campusScreen'])->name('attendance.qr');
        Route::get('attendance/campus-qr/token', [Manage\AttendanceController::class, 'campusToken'])->name('attendance.qr-token');

        // Lessons — mentors add and edit lessons of their own courses (same pages as admin)
        Route::get('lessons', [Admin\CurriculumController::class, 'lessons'])->name('lessons.index');
        Route::post('lessons', [Admin\CurriculumController::class, 'storeLessonForCourse'])->name('lessons.create');
        Route::get('courses/{course}/curriculum', [Admin\CurriculumController::class, 'show'])->name('courses.curriculum');
        Route::post('courses/{course}/modules', [Admin\CurriculumController::class, 'storeModule'])->name('modules.store');
        Route::put('modules/{module}', [Admin\CurriculumController::class, 'updateModule'])->name('modules.update');
        Route::delete('modules/{module}', [Admin\CurriculumController::class, 'destroyModule'])->name('modules.destroy');
        Route::post('modules/{module}/lessons', [Admin\CurriculumController::class, 'storeLesson'])->name('lessons.store');
        Route::put('lessons/{lesson}', [Admin\CurriculumController::class, 'updateLesson'])->name('lessons.update');
        Route::delete('lessons/{lesson}', [Admin\CurriculumController::class, 'destroyLesson'])->name('lessons.destroy');
        Route::post('courses/{course}/reorder', [Admin\CurriculumController::class, 'reorder'])->name('courses.reorder');
        Route::post('ai/courses/{course}/lesson', [Admin\AiController::class, 'lessonContent'])->middleware('throttle:20,1')->name('ai.lesson-content');

        // Module 4 — results, certificates, mentor pages
        Route::get('progress', [Manage\ProgressController::class, 'index'])->name('progress.index');
        Route::post('progress/recalculate', [Manage\ProgressController::class, 'recalculate'])->middleware('throttle:6,1')->name('progress.recalculate');
        Route::get('progress.csv', [Manage\ProgressController::class, 'export'])->name('progress.export');
        Route::post('progress/{enrollment}/certificate', [Manage\ProgressController::class, 'issue'])->name('progress.issue');
        Route::post('certificates/{certificate}/revoke', [Manage\ProgressController::class, 'revoke'])->name('certificates.revoke');

        Route::get('my-page', [Admin\MentorController::class, 'mine'])->name('mentors.mine');
        Route::get('mentors/{mentor}/page', [Admin\MentorController::class, 'edit'])->name('mentors.edit');
        Route::post('mentors/{mentor}/page', [Admin\MentorController::class, 'update'])->name('mentors.update');
        Route::post('mentors/{mentor}/ai', [Admin\MentorController::class, 'ai'])->middleware('throttle:10,1')->name('mentors.ai');
        Route::post('mentors/{mentor}/story-ai', [Admin\MentorController::class, 'storyAi'])->middleware('throttle:20,1')->name('mentors.story-ai');
    });

    // ---------------- Franchise partner (read-only: their own students) ----------------
    Route::middleware('role:'.User::ROLE_FRANCHISER)->prefix('partner')->name('partner.')->group(function () {
        Route::get('/', \App\Http\Controllers\Partner\DashboardController::class)->name('dashboard');
        Route::get('/students.csv', [\App\Http\Controllers\Partner\DashboardController::class, 'export'])->name('export');
    });

    // Start a class as host (mentor of the class, or admin)
    Route::get('/host/classes/{session}/start', [Mentor\ClassController::class, 'start'])->name('host.classes.start');

    // ---------------- Admin ----------------
    Route::middleware('role:'.User::ROLE_SUPER_ADMIN.'|'.User::ROLE_ADMIN)->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::resource('courses', Admin\CourseController::class)->except('show');
        Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::post('reports/ai', [Admin\ReportController::class, 'ai'])->middleware('throttle:6,1')->name('reports.ai');
        Route::get('reports.csv', [Admin\ReportController::class, 'export'])->name('reports.export');
        Route::get('passports/import', [Admin\PassportImportController::class, 'index'])->name('passports.index');
        Route::get('passports/template.csv', [Admin\PassportImportController::class, 'template'])->name('passports.template');
        Route::post('passports/import', [Admin\PassportImportController::class, 'store'])->name('passports.import');
        Route::get('franchise', [Admin\FranchiseController::class, 'index'])->name('franchise.index');
        Route::post('franchise', [Admin\FranchiseController::class, 'store'])->name('franchise.store');
        Route::get('mentors', [Admin\MentorController::class, 'index'])->name('mentors.index');
        Route::post('mentors', [Admin\MentorController::class, 'store'])->name('mentors.store');
        Route::get('courses/{course}/curriculum', [Admin\CurriculumController::class, 'show'])->name('courses.curriculum');
        Route::post('courses/{course}/modules', [Admin\CurriculumController::class, 'storeModule'])->name('modules.store');
        Route::put('modules/{module}', [Admin\CurriculumController::class, 'updateModule'])->name('modules.update');
        Route::delete('modules/{module}', [Admin\CurriculumController::class, 'destroyModule'])->name('modules.destroy');
        Route::post('modules/{module}/lessons', [Admin\CurriculumController::class, 'storeLesson'])->name('lessons.store');
        Route::put('lessons/{lesson}', [Admin\CurriculumController::class, 'updateLesson'])->name('lessons.update');
        Route::delete('lessons/{lesson}', [Admin\CurriculumController::class, 'destroyLesson'])->name('lessons.destroy');
        Route::post('courses/{course}/reorder', [Admin\CurriculumController::class, 'reorder'])->name('courses.reorder');
        Route::get('lessons', [Admin\CurriculumController::class, 'lessons'])->name('lessons.index');
        Route::post('lessons', [Admin\CurriculumController::class, 'storeLessonForCourse'])->name('lessons.create');

        Route::resource('batches', Admin\BatchController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('enrollments', [Admin\EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::post('enrollments', [Admin\EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::post('enrollments/import', [Admin\EnrollmentController::class, 'import'])->name('enrollments.import');
        Route::get('enrollments/template', [Admin\EnrollmentController::class, 'template'])->name('enrollments.template');
        Route::put('enrollments/{enrollment}', [Admin\EnrollmentController::class, 'update'])->name('enrollments.update');

        Route::get('payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}/screenshot', [Admin\PaymentController::class, 'screenshot'])->name('payments.screenshot');
        Route::post('payments/{payment}/approve', [Admin\PaymentController::class, 'approve'])->name('payments.approve');
        Route::post('payments/{payment}/reject', [Admin\PaymentController::class, 'reject'])->name('payments.reject');
        Route::post('payments/{payment}/refund', [Admin\PaymentController::class, 'refund'])->name('payments.refund');

        Route::resource('coupons', Admin\CouponController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('students', [Admin\StudentController::class, 'index'])->name('students.index');
        Route::get('students/{user}', [Admin\StudentController::class, 'show'])->name('students.show');
        Route::post('students/{user}/action', [Admin\StudentController::class, 'action'])->name('students.action');

        // AI tools (drafts only; rate limited)
        Route::middleware('throttle:30,1')->prefix('ai')->name('ai.')->group(function () {
            Route::post('course-draft', [Admin\AiController::class, 'courseDraft'])->name('course-draft');
            Route::post('courses/{course}/outline', [Admin\AiController::class, 'courseOutline'])->name('course-outline');
            Route::post('courses/{course}/lesson', [Admin\AiController::class, 'lessonContent'])->name('lesson-content');
            Route::post('payments/{payment}/check', [Admin\AiController::class, 'paymentCheck'])->name('payment-check');
        });

        // Live classes (Module 2)
        Route::get('classes', [Admin\ClassController::class, 'index'])->name('classes.index');
        Route::post('classes', [Admin\ClassController::class, 'store'])->name('classes.store');
        Route::get('classes/{session}', [Admin\ClassController::class, 'show'])->name('classes.show');
        Route::post('classes/{session}/reschedule', [Admin\ClassController::class, 'reschedule'])->name('classes.reschedule');
        Route::post('classes/{session}/cancel', [Admin\ClassController::class, 'cancel'])->name('classes.cancel');
        Route::post('classes/{session}/retry-zoom', [Admin\ClassController::class, 'retryZoom'])->name('classes.retry');
        Route::post('classes/{session}/attendance/{user}', [Admin\ClassController::class, 'mark'])->name('classes.mark');
        Route::get('classes/{session}/attendance.csv', [Admin\ClassController::class, 'export'])->name('classes.export');
        Route::post('classes/{session}/notes/ai', [Admin\ClassController::class, 'generateNotes'])->middleware('throttle:10,1')->name('classes.notes.ai');
        Route::put('classes/{session}/notes', [Admin\ClassController::class, 'saveNotes'])->name('classes.notes.save');

        Route::middleware('role:'.User::ROLE_SUPER_ADMIN)->group(function () {
            Route::get('zoom', [Admin\ZoomAccountController::class, 'index'])->name('zoom.index');
            Route::post('zoom', [Admin\ZoomAccountController::class, 'store'])->name('zoom.store');
            Route::put('zoom/{zoomAccount}', [Admin\ZoomAccountController::class, 'update'])->name('zoom.update');
            Route::post('zoom/{zoomAccount}/test', [Admin\ZoomAccountController::class, 'test'])->name('zoom.test');
            Route::delete('zoom/{zoomAccount}', [Admin\ZoomAccountController::class, 'destroy'])->name('zoom.destroy');
        });

        Route::get('activity', [Admin\ActivityController::class, 'index'])->name('activity.index');
        Route::get('settings', [Admin\ActivityController::class, 'settings'])
            ->middleware('role:'.User::ROLE_SUPER_ADMIN)->name('settings.edit');
        Route::put('settings', [Admin\ActivityController::class, 'saveSettings'])
            ->middleware('role:'.User::ROLE_SUPER_ADMIN)->name('settings.update');
        Route::post('settings/test-whatsapp', [Admin\ActivityController::class, 'testWhatsapp'])
            ->middleware(['role:'.User::ROLE_SUPER_ADMIN, 'throttle:5,1'])->name('settings.test-whatsapp');
        Route::post('settings/test-mail', [Admin\ActivityController::class, 'testMail'])
            ->middleware(['role:'.User::ROLE_SUPER_ADMIN, 'throttle:5,1'])->name('settings.test-mail');
    });
});

require __DIR__.'/auth.php';
