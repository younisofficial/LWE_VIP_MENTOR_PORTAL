<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MentorProfile extends Model
{
    protected $fillable = [
        'user_id', 'slug', 'headline', 'bio', 'photo_path', 'cover_path', 'expertise', 'highlights', 'success_stories',
        'socials', 'intro_video_url', 'whatsapp', 'is_published',
        'packages', 'review_videos', 'faqs', 'seo_title', 'seo_description', 'pixel_id', 'cta_note',
        'journey', 'outcomes', 'platforms', 'timeline', 'offer_ends_at', 'seats_total', 'seats_left',
    ];

    protected function casts(): array
    {
        return ['expertise' => 'array', 'highlights' => 'array', 'success_stories' => 'array', 'socials' => 'array', 'is_published' => 'boolean',
            'packages' => 'array', 'review_videos' => 'array', 'faqs' => 'array',
            'journey' => 'array', 'outcomes' => 'array', 'platforms' => 'array', 'timeline' => 'array',
            'offer_ends_at' => 'datetime', 'seats_total' => 'integer', 'seats_left' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'mentor';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** YouTube video id from any YouTube link (watch, youtu.be, shorts, live, embed), else null. */
    public static function youtubeId(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~', $url, $m);

        return $m[1] ?? null;
    }

    /** Public URL for an image on the public disk. */
    public static function imageUrl(?string $path): ?string
    {
        return $path ? asset('storage/'.ltrim($path, '/')) : null; // same host the page is opened on
    }
}
