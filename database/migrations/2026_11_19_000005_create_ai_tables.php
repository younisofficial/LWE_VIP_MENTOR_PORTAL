<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every AI call is logged: who, which feature, tokens used, success or error.
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 50)->index();
            $table->string('model', 60);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->enum('status', ['ok', 'error'])->default('ok');
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->json('ai_check')->nullable()->after('review_note');
            $table->timestamp('ai_checked_at')->nullable()->after('ai_check');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['ai_check', 'ai_checked_at']));
        Schema::dropIfExists('ai_logs');
    }
};
