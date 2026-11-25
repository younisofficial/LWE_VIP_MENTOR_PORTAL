<?php

use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use App\Models\ZoomAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Database\Seeders\RolesSeeder;

function lweSeedRoles(): void
{
    (new RolesSeeder)->run();
}

function lweUser(string $role = User::ROLE_STUDENT, array $attrs = []): User
{
    static $n = 0;
    $n++;
    $user = User::factory()->create(['phone' => '9230011'.str_pad((string) $n, 5, '0', STR_PAD_LEFT), ...$attrs]);
    $user->assignRole($role);

    return $user;
}

function lweCourse(?float $fee = 10000, array $attrs = []): Course
{
    $course = Course::create(['title' => 'Test Course '.uniqid(), 'fee' => $fee, 'status' => 'published', ...$attrs]);
    $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
    $module->lessons()->create(['course_id' => $course->id, 'title' => 'Lesson A', 'type' => 'reading', 'position' => 0]);
    $module->lessons()->create(['course_id' => $course->id, 'title' => 'Lesson B', 'type' => 'reading', 'position' => 1, 'unlock_type' => 'after_previous']);

    return $course;
}

function lweBatch(Course $course, array $attrs = []): Batch
{
    return Batch::create([
        'course_id' => $course->id, 'name' => 'Batch 1', 'starts_on' => now()->toDateString(),
        'class_days' => ['mon', 'wed'], 'class_time' => '20:00', 'class_duration_minutes' => 90, 'status' => 'running',
        ...$attrs,
    ]);
}

function lweZoomAccount(string $name = 'Zoom 1'): ZoomAccount
{
    return ZoomAccount::create([
        'name' => $name, 'host_email' => strtolower(str_replace(' ', '', $name)).'@lwe.test',
        'account_id' => 'acc', 'client_id' => 'cid', 'client_secret' => 'csecret',
        'sdk_key' => 'sdkkey', 'sdk_secret' => 'sdksecret', 'webhook_secret' => 'whsecret', 'status' => 'ok',
    ]);
}

function lweFakeZoom(): void
{
    Http::fake([
        'zoom.us/oauth/token*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        'api.zoom.us/v2/users/*/meetings' => Http::sequence()->whenEmpty(Http::response(['id' => 98765432101, 'password' => 'pw1'], 201)),
        'api.zoom.us/v2/users/*/token*' => Http::response(['token' => 'zak123']),
        'api.zoom.us/v2/meetings/*' => Http::response(null, 204),
        'api.zoom.us/v2/users/*' => Http::response(['first_name' => 'Host', 'last_name' => 'User', 'type' => 2]),
    ]);
}

/** Next given weekday (e.g. 'monday') at least 2 days ahead, as Y-m-d in Pakistan time. */
function lweNextDay(string $day): string
{
    return CarbonImmutable::now(config('lwe.timezone'))->addDays(2)->next($day)->format('Y-m-d');
}

function lweZoomPost($test, array $body)
{
    $raw = json_encode($body);
    $ts = (string) time();

    return $test->call('POST', '/webhooks/zoom', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_ZM_REQUEST_TIMESTAMP' => $ts,
        'HTTP_X_ZM_SIGNATURE' => 'v0='.hash_hmac('sha256', "v0:{$ts}:{$raw}", 'whsecret'),
    ], $raw);
}

/**
 * Act as a user in a test that switches between several users.
 * The single-device rule logs a user out when the session holds another login's token,
 * so clear this user's token first (as if they just logged in on this browser).
 */
function lweAs($test, User $user)
{
    $user->forceFill(['current_session_id' => null])->saveQuietly();

    return $test->actingAs($user);
}
