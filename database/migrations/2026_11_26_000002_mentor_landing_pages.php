<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mentor landing pages: price packages, review videos, FAQs, SEO text and a per-mentor ad pixel. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->json('packages')->nullable()->after('success_stories');      // up to 3: name, tagline, old_price, price, features[], badge, link
            $table->json('review_videos')->nullable()->after('packages');        // [{title, name, url}]
            $table->json('faqs')->nullable()->after('review_videos');            // [{q, a}]
            $table->string('seo_title', 70)->nullable()->after('faqs');
            $table->string('seo_description', 170)->nullable()->after('seo_title');
            $table->string('pixel_id', 30)->nullable()->after('seo_description'); // extra Meta pixel just for this mentor's ads
            $table->string('cta_note', 150)->nullable()->after('pixel_id');      // e.g. "Next batch starts 1 December — 40 seats"
        });
    }

    public function down(): void
    {
        Schema::table('mentor_profiles', fn (Blueprint $t) => $t->dropColumn(['packages', 'review_videos', 'faqs', 'seo_title', 'seo_description', 'pixel_id', 'cta_note']));
    }
};
