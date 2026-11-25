<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MentorProfile;
use App\Models\User;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\ProfileAi;
use App\Services\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mentors and their public landing pages (/mentors/{slug}).
 * Admins manage every mentor; a mentor can edit their own page.
 */
class MentorController extends Controller
{
    private function guard(Request $request, User $mentor): void
    {
        abort_unless($mentor->hasRole(User::ROLE_MENTOR), 404);
        abort_unless($request->user()->isStaff() || $request->user()->id === $mentor->id, 403);
    }

    private function profileFor(User $mentor): MentorProfile
    {
        return $mentor->mentorProfile ?? $mentor->mentorProfile()->create(['slug' => MentorProfile::uniqueSlug($mentor->name)]);
    }

    public function index(): Response
    {
        $mentors = User::role(User::ROLE_MENTOR)->with(['mentorProfile', 'mentoredCourses:id,title'])->orderBy('name')->get()
            ->map(fn (User $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'phone' => Phone::display($m->phone),
                'email' => $m->email,
                'courses' => $m->mentoredCourses->pluck('title'),
                'published' => (bool) $m->mentorProfile?->is_published,
                'url' => $m->mentorProfile ? route('mentors.show', $m->mentorProfile->slug) : null,
                'photo' => MentorProfile::imageUrl($m->mentorProfile?->photo_path),
            ]);

        return Inertia::render('Admin/Mentors/Index', ['mentors' => $mentors]);
    }

    /** Add a mentor account (they log in with phone/email + this password). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);
        $phone = Phone::normalize($data['phone']);
        if (! $phone) {
            return back()->withErrors(['phone' => 'Enter a valid Pakistani mobile number.']);
        }
        if (User::where('phone', $phone)->exists()) {
            return back()->withErrors(['phone' => 'This phone number already has an account. Ask a super admin to change its role.']);
        }

        $mentor = User::create([...$data, 'phone' => $phone, 'password' => Hash::make($data['password']), 'status' => 'active']);
        $mentor->assignRole(User::ROLE_MENTOR);
        $this->profileFor($mentor);

        return redirect()->route('manage.mentors.edit', $mentor)->with('success', 'Mentor added. Now fill in their landing page — AI can write it from a few notes.');
    }

    public function edit(Request $request, User $mentor): Response
    {
        $this->guard($request, $mentor);
        $p = $this->profileFor($mentor);

        return Inertia::render('Admin/Mentors/Edit', [
            'mentor' => ['id' => $mentor->id, 'name' => $mentor->name],
            'profile' => [
                ...$p->only('slug', 'headline', 'bio', 'intro_video_url', 'whatsapp', 'is_published', 'seo_title', 'seo_description', 'pixel_id', 'cta_note'),
                'packages' => $p->packages ?? [],
                'review_videos' => $p->review_videos ?? [],
                'faqs' => $p->faqs ?? [],
                'journey' => $p->journey ?? [],
                'outcomes' => $p->outcomes ?? [],
                'platforms' => $p->platforms ?? [],
                'timeline' => $p->timeline ?? [],
                'offer_ends_at' => $p->offer_ends_at?->timezone(config('lwe.timezone'))->format('Y-m-d\\TH:i'),
                'seats_total' => $p->seats_total,
                'seats_left' => $p->seats_left,
                'expertise' => $p->expertise ?? [],
                'highlights' => $p->highlights ?? [],
                'socials' => (object) ($p->socials ?? []),
                'success_stories' => collect($p->success_stories ?? [])->map(fn ($s) => [...$s, 'image_url' => MentorProfile::imageUrl($s['image_path'] ?? null)])->values(),
                'photo_url' => MentorProfile::imageUrl($p->photo_path),
                'cover_url' => MentorProfile::imageUrl($p->cover_path),
                'public_url' => route('mentors.show', $p->slug),
            ],
            'isSelf' => $request->user()->id === $mentor->id,
        ]);
    }

    public function update(Request $request, User $mentor): RedirectResponse
    {
        $this->guard($request, $mentor);
        $p = $this->profileFor($mentor);
        // Prices and seats may arrive as "7,000" or "Rs 7000": keep only the digits.
        $digits = fn ($v) => ($d = preg_replace('/\D/', '', (string) $v)) === '' ? null : $d;
        $request->merge([
            'packages' => collect($request->input('packages', []))->map(fn ($pk) => is_array($pk)
                ? [...$pk, 'price' => $digits($pk['price'] ?? null), 'old_price' => $digits($pk['old_price'] ?? null)] : $pk)->all(),
            'seats_total' => $digits($request->input('seats_total')),
            'seats_left' => $digits($request->input('seats_left')),
        ]);
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('mentor_profiles', 'slug')->ignore($p->id)],
            'headline' => ['nullable', 'string', 'max:200'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'intro_video_url' => ['nullable', 'url', 'max:500'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'is_published' => ['boolean'],
            'expertise' => ['nullable', 'array', 'max:12'],
            'expertise.*' => ['string', 'max:40'],
            'highlights' => ['nullable', 'array', 'max:6'],
            'highlights.*.value' => ['required', 'string', 'max:20'],
            'highlights.*.label' => ['required', 'string', 'max:40'],
            'socials' => ['nullable', 'array'],
            'socials.*' => ['nullable', 'url', 'max:300'],
            'success_stories' => ['nullable', 'array', 'max:12'],
            'success_stories.*.name' => ['required', 'string', 'max:80'],
            'success_stories.*.role' => ['nullable', 'string', 'max:100'],
            'success_stories.*.story' => ['required', 'string', 'max:2000'],
            'success_stories.*.result' => ['nullable', 'string', 'max:200'],
            'success_stories.*.image_path' => ['nullable', 'string', 'max:300'],
            'story_images' => ['nullable', 'array'],
            'story_images.*' => ['nullable', 'image', 'max:3072'],
            'photo' => ['nullable', 'image', 'max:3072'],
            'cover' => ['nullable', 'image', 'max:5120'],
            // Price packages (up to 3), review videos, FAQs, SEO and ads
            'packages' => ['nullable', 'array', 'max:3'],
            'packages.*.name' => ['required', 'string', 'max:60'],
            'packages.*.tagline' => ['nullable', 'string', 'max:120'],
            'packages.*.old_price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'packages.*.price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'packages.*.features' => ['nullable', 'array', 'max:12'],
            'packages.*.features.*' => ['nullable', 'string', 'max:120'],
            'packages.*.badge' => ['nullable', 'string', 'max:30'],
            'packages.*.link' => ['nullable', 'url', 'max:500'],
            'packages.*.featured' => ['boolean'],
            'review_videos' => ['nullable', 'array', 'max:12'],
            'review_videos.*.url' => ['required', 'url', 'max:300', function ($attr, $value, $fail) {
                if (! MentorProfile::youtubeId($value)) {
                    $fail('Review videos must be YouTube links.');
                }
            }],
            'review_videos.*.name' => ['nullable', 'string', 'max:80'],
            'review_videos.*.title' => ['nullable', 'string', 'max:120'],
            'faqs' => ['nullable', 'array', 'max:12'],
            'faqs.*.q' => ['required', 'string', 'max:200'],
            'faqs.*.a' => ['required', 'string', 'max:1500'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:170'],
            'pixel_id' => ['nullable', 'regex:/^[0-9]{6,20}$/'],
            'cta_note' => ['nullable', 'string', 'max:150'],
            'journey' => ['nullable', 'array', 'max:16'],
            'journey.*.title' => ['required', 'string', 'max:40'],
            'journey.*.points' => ['nullable', 'array', 'max:3'],
            'journey.*.points.*' => ['nullable', 'string', 'max:60'],
            'outcomes' => ['nullable', 'array', 'max:6'],
            'outcomes.*.title' => ['required', 'string', 'max:50'],
            'outcomes.*.text' => ['nullable', 'string', 'max:160'],
            'platforms' => ['nullable', 'array', 'max:8'],
            'platforms.*' => ['nullable', 'string', 'max:20'],
            'timeline' => ['nullable', 'array', 'max:8'],
            'timeline.*.year' => ['required', 'string', 'max:12'],
            'timeline.*.text' => ['required', 'string', 'max:140'],
            'offer_ends_at' => ['nullable', 'date'],
            'seats_total' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'seats_left' => ['nullable', 'integer', 'min:0', 'max:100000', Rule::when($request->filled('seats_total'), ['lte:seats_total'])],
        ], [
            'seats_left.lte' => 'Seats left cannot be more than total seats.',
            'slug.regex' => 'Use small letters, numbers and dashes only, e.g. haider-qazi.',
            'packages.max' => 'Up to 3 packages.',
            'packages.*.price.required' => 'Package :position needs a price — numbers only, e.g. 7000.',
            'packages.*.name.required' => 'Package :position needs a name.',
            'pixel_id.regex' => 'The pixel ID is a number only.',
        ]);

        // Story pictures: a new upload replaces the old one; keep paths only from this profile.
        $old = collect($p->success_stories ?? [])->pluck('image_path')->filter()->all();
        $stories = collect($data['success_stories'] ?? [])->values()->map(function ($s, $i) use ($request, $old) {
            if ($request->hasFile("story_images.{$i}")) {
                $s['image_path'] = $request->file("story_images.{$i}")->store('mentors/stories', 'public');
            } elseif (! in_array($s['image_path'] ?? null, $old, true)) {
                $s['image_path'] = null;
            }

            return collect($s)->only('name', 'role', 'story', 'result', 'image_path')->all();
        });
        // Delete story images that are no longer used.
        foreach (array_diff($old, $stories->pluck('image_path')->filter()->all()) as $gone) {
            Storage::disk('public')->delete($gone);
        }

        $attrs = [
            ...collect($data)->only('slug', 'headline', 'bio', 'intro_video_url', 'whatsapp', 'is_published')->all(),
            'expertise' => array_values(array_filter($data['expertise'] ?? [])),
            'highlights' => array_values($data['highlights'] ?? []),
            'socials' => array_filter($data['socials'] ?? []),
            'success_stories' => $stories->all(),
            'packages' => collect($data['packages'] ?? [])->map(fn ($pk) => [
                'name' => $pk['name'],
                'tagline' => $pk['tagline'] ?? null,
                'old_price' => isset($pk['old_price']) && (int) $pk['old_price'] > (int) $pk['price'] ? (int) $pk['old_price'] : null,
                'price' => (int) $pk['price'],
                'features' => array_values(array_filter(array_map(fn ($f) => trim((string) $f), $pk['features'] ?? []))),
                'badge' => $pk['badge'] ?? null,
                'link' => $pk['link'] ?? null,
                'featured' => (bool) ($pk['featured'] ?? false),
            ])->values()->all(),
            'review_videos' => collect($data['review_videos'] ?? [])->map(fn ($v) => collect($v)->only('url', 'name', 'title')->all())->values()->all(),
            'faqs' => collect($data['faqs'] ?? [])->map(fn ($f) => collect($f)->only('q', 'a')->all())->values()->all(),
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'pixel_id' => $data['pixel_id'] ?? null,
            'cta_note' => $data['cta_note'] ?? null,
            'journey' => collect($data['journey'] ?? [])->map(fn ($j) => [
                'title' => $j['title'],
                'points' => array_values(array_filter(array_map(fn ($x) => trim((string) $x), $j['points'] ?? []))),
            ])->values()->all(),
            'outcomes' => collect($data['outcomes'] ?? [])->map(fn ($o) => ['title' => $o['title'], 'text' => $o['text'] ?? null])->values()->all(),
            'platforms' => array_values(array_filter(array_map(fn ($x) => trim((string) $x), $data['platforms'] ?? []))),
            'timeline' => collect($data['timeline'] ?? [])->map(fn ($t) => ['year' => $t['year'], 'text' => $t['text']])->values()->all(),
            // Entered in Pakistan time, stored as UTC.
            'offer_ends_at' => ! empty($data['offer_ends_at']) ? \Illuminate\Support\Carbon::parse($data['offer_ends_at'], config('lwe.timezone'))->utc() : null,
            'seats_total' => $data['seats_total'] ?? null,
            'seats_left' => $data['seats_left'] ?? null,
        ];
        foreach (['photo' => 'photo_path', 'cover' => 'cover_path'] as $field => $col) {
            if ($request->hasFile($field)) {
                if ($p->{$col}) {
                    Storage::disk('public')->delete($p->{$col});
                }
                $attrs[$col] = $request->file($field)->store('mentors', 'public');
            }
        }
        if (! empty($attrs['whatsapp'])) {
            $attrs['whatsapp'] = Phone::normalize($attrs['whatsapp']) ?? $attrs['whatsapp'];
        }
        $p->update($attrs);

        return back()->with('success', $p->is_published ? 'Saved. The page is live.' : 'Saved as hidden. Tick "Show this page publicly" when ready.');
    }

    /** AI writes headline, bio, expertise and highlights from rough notes. */
    public function ai(Request $request, User $mentor, ProfileAi $ai): JsonResponse
    {
        $this->guard($request, $mentor);
        $data = $request->validate(['notes' => ['required', 'string', 'min:20', 'max:4000'], 'language' => ['required', Rule::in(['english', 'roman_urdu', 'urdu'])]]);
        try {
            return response()->json($ai->mentorPage($mentor->name, $data['notes'], $data['language'], $request->user()));
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** AI cleans up one success story (facts kept). */
    public function storyAi(Request $request, User $mentor, ProfileAi $ai): JsonResponse
    {
        $this->guard($request, $mentor);
        $data = $request->validate(['rough' => ['required', 'string', 'min:15', 'max:3000'], 'language' => ['required', Rule::in(['english', 'roman_urdu', 'urdu'])]]);
        try {
            return response()->json($ai->polishStory($data['rough'], $data['language'], $request->user()));
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** "My public page" link in the mentor menu. */
    public function mine(Request $request): RedirectResponse
    {
        return redirect()->route('manage.mentors.edit', $request->user());
    }
}
