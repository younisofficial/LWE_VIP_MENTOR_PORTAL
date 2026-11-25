<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mentor page v2: journey stages, outcomes, platforms, timeline, offer countdown and seats. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->json('journey')->nullable();      // [{title, points:[..3]}] — the animated branching line
            $table->json('outcomes')->nullable();     // [{title, text}] — "What you will be able to do"
            $table->json('platforms')->nullable();    // ["Amazon", "Daraz", ...] — "Students now sell on"
            $table->json('timeline')->nullable();     // [{year, text}] — the mentor's own story
            $table->dateTime('offer_ends_at')->nullable();
            $table->unsignedInteger('seats_total')->nullable();
            $table->unsignedInteger('seats_left')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mentor_profiles', fn (Blueprint $t) => $t->dropColumn(['journey', 'outcomes', 'platforms', 'timeline', 'offer_ends_at', 'seats_total', 'seats_left']));
    }
};
