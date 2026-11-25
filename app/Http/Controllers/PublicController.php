<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\MentorProfile;
use App\Models\User;
use App\Services\PassportService;
use App\Services\Phone;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Pages anyone can open without logging in: mentor pages, Skill Passports, certificate checks. */
class PublicController extends Controller
{
    /** All published mentors. Plain HTML (Blade), so Google and ad crawlers read it fully. */
    public function mentors(): View
    {
        $profiles = MentorProfile::with('user:id,name')->where('is_published', true)->orderBy('id')->get()
            ->map(fn ($p) => [
                'slug' => $p->slug,
                'name' => $p->user->name,
                'headline' => $p->headline,
                'photo' => MentorProfile::imageUrl($p->photo_path),
                'expertise' => array_slice($p->expertise ?? [], 0, 3),
                'from_price' => collect($p->packages ?? [])->pluck('price')->filter(fn ($v) => $v > 0)->min(),
            ]);

        return view('public.mentors', ['mentors' => $profiles, 'tracking' => $this->tracking()]);
    }

    /** One mentor's landing page: /mentors/{name}. */
    public function mentor(string $slug): View
    {
        $p = MentorProfile::with('user')->where('slug', $slug)->firstOrFail();
        $viewer = auth()->user();
        abort_unless($p->is_published || ($viewer && ($viewer->isStaff() || $viewer->id === $p->user_id)), 404);

        $courses = Course::published()->whereHas('mentors', fn ($q) => $q->whereKey($p->user_id))->get(['id', 'title', 'slug', 'short_description', 'fee', 'thumbnail_path', 'is_vip']);
        $whatsapp = $p->whatsapp ?: (string) Setting::get('site_whatsapp');
        $name = $p->user->name;

        $packages = collect($p->packages ?? [])->values()->map(fn ($pk) => [
            ...$pk,
            'off' => ! empty($pk['old_price']) && $pk['old_price'] > $pk['price'] ? (int) round((1 - $pk['price'] / $pk['old_price']) * 100) : 0,
            // Button: the package's own link, else WhatsApp with the package written in.
            'href' => ($pk['link'] ?? null) ?: $this->whatsappLink($whatsapp, "Assalam o Alaikum! I want to join {$name}'s {$pk['name']} package (PKR ".number_format($pk['price']).').'),
        ]);
        // Without "featured", the middle of three is the highlighted one.
        if ($packages->count() && ! $packages->contains('featured', true)) {
            $mid = $packages->count() === 3 ? 1 : 0;
            $packages = $packages->map(fn ($pk, $i) => [...$pk, 'featured' => $i === $mid && $packages->count() > 1]);
        }

        $title = $p->seo_title ?: Str::limit($name.($p->headline ? ' — '.$p->headline : ''), 60, '');
        $description = $p->seo_description ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($p->bio ?: $p->headline)))), 158);

        return view('public.mentor', [
            'p' => $p,
            'name' => $name,
            'title' => $title,
            'description' => $description,
            'canonical' => route('mentors.show', $p->slug),
            'photo' => MentorProfile::imageUrl($p->photo_path),
            'cover' => MentorProfile::imageUrl($p->cover_path),
            'packages' => $packages,
            'videos' => collect($p->review_videos ?? [])->map(fn ($v) => [...$v, 'id' => MentorProfile::youtubeId($v['url'] ?? null)])->filter(fn ($v) => $v['id'])->values(),
            'introId' => MentorProfile::youtubeId($p->intro_video_url),
            'stories' => collect($p->success_stories ?? [])->map(fn ($s) => [...$s, 'image_url' => MentorProfile::imageUrl($s['image_path'] ?? null)]),
            'faqs' => collect($p->faqs ?? []),
            'courses' => $courses->map(fn ($c) => [
                'title' => $c->title, 'short_description' => $c->short_description, 'is_vip' => $c->is_vip,
                'fee' => $c->fee ? 'PKR '.number_format((float) $c->fee) : 'Free',
                'thumbnail' => $c->thumbnail_path ? asset('storage/'.ltrim($c->thumbnail_path, '/')) : null,
            ]),
            'students' => Enrollment::whereIn('course_id', $courses->pluck('id'))->whereIn('status', ['active', 'completed'])->distinct()->count('user_id'),
            'whatsappHref' => $this->whatsappLink($whatsapp, "Assalam o Alaikum! I want to know more about {$name}'s mentorship."),
            'preview' => ! $p->is_published,
            'tracking' => $this->tracking($p->pixel_id),
            'journey' => collect($p->journey ?? []),
            'outcomes' => collect($p->outcomes ?? []),
            'platforms' => collect($p->platforms ?? []),
            'timeline' => collect($p->timeline ?? []),
            // Countdown only while the offer is still running.
            'offerEndsAt' => $p->offer_ends_at && $p->offer_ends_at->isFuture() ? $p->offer_ends_at->toIso8601String() : null,
            'seats' => $p->seats_total ? ['left' => $p->seats_left ?? $p->seats_total, 'total' => $p->seats_total] : null,
            // Compare table: every feature of every package, ticked where included.
            'compare' => $packages->count() < 2 ? collect() : $packages->flatMap(fn ($pk) => $pk['features'] ?? [])->unique()->take(12)->values()
                ->map(fn ($f) => ['feature' => $f, 'has' => $packages->map(fn ($pk) => in_array($f, $pk['features'] ?? [], true))->all()]),
            'certificateCourse' => $courses->first()?->title,
        ]);
    }

    /** sitemap.xml for Google: the mentors list and every published mentor page. */
    public function sitemap(): HttpResponse
    {
        $profiles = MentorProfile::where('is_published', true)->get(['slug', 'updated_at']);

        return response()->view('public.sitemap', ['profiles' => $profiles], 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): HttpResponse
    {
        $lines = ['User-agent: *', 'Disallow: /admin/', 'Disallow: /manage/', 'Disallow: /mentor/', 'Disallow: /partner/', 'Disallow: /qa', 'Disallow: /login', 'Allow: /mentors', 'Allow: /verify/', 'Allow: /passport/', 'Sitemap: '.route('sitemap')];

        return response(implode("\n", $lines)."\n", 200)->header('Content-Type', 'text/plain');
    }

    private function whatsappLink(?string $number, string $text): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $number);

        return $digits ? 'https://wa.me/'.$digits.'?text='.rawurlencode($text) : null;
    }

    /** Pixel ids for the page: the site pixel from Settings plus an optional extra one. */
    private function tracking(?string $extraPixel = null): array
    {
        $pixels = array_values(array_unique(array_filter([(string) Setting::get('meta_pixel_id'), (string) $extraPixel])));

        return ['pixels' => $pixels, 'ga4' => (string) Setting::get('ga4_id')];
    }

    public function passport(string $code, PassportService $passports): Response
    {
        $user = User::where('passport_code', $code)->firstOrFail();
        $viewer = auth()->user();
        abort_unless($user->passport_public || ($viewer && ($viewer->id === $user->id || $viewer->isStaff())), 404);

        $data = $passports->build($user);
        $data['student']['phone'] = null; // never public
        $data['courses'] = collect($data['courses'])->filter(fn ($c) => $c['status'] === 'completed' || $c['score'] !== null)->values();

        return Inertia::render('Public/Passport', [
            ...$data,
            'verifyUrl' => route('passport.show', $code),
            'isOwner' => $viewer?->id === $user->id,
        ]);
    }

    public function certificate(string $code): Response
    {
        $c = Certificate::with(['user:id,name,passport_code,passport_public', 'course:id,title', 'course.skills:id,name', 'course.mentors:id,name', 'enrollment.batch:id,name'])
            ->where('code', $code)->first();

        return Inertia::render('Public/Certificate', [
            'found' => (bool) $c,
            'code' => $code,
            'certificate' => $c ? [
                'code' => $c->code,
                'name' => $c->user->name,
                'course' => $c->course->title,
                'skills' => $c->course->skills->pluck('name'),
                'mentors' => $c->course->mentors->pluck('name'),
                'batch' => $c->enrollment?->batch?->name,
                'grade' => $c->grade,
                'score' => $c->score,
                'issued' => $c->issued_at->format('d F Y'),
                'valid' => $c->isValid(),
                'revoke_reason' => $c->revoke_reason,
                'passport_url' => $c->user->passport_code && $c->user->passport_public ? route('passport.show', $c->user->passport_code) : null,
            ] : null,
            'verifyUrl' => route('certificates.show', $code),
        ]);
    }
}
