<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Q&A becomes one board per batch: every student and mentor of the batch sees and answers
 * every question, whatever course it is about. The course is now optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qa_questions', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->change();
            $table->index(['batch_id', 'status']);
        });

        // Old questions: put them in the asker's batch for that course.
        DB::table('qa_questions')->whereNull('batch_id')->orderBy('id')->each(function ($q) {
            $batchId = DB::table('enrollments')->where('user_id', $q->user_id)->where('course_id', $q->course_id)
                ->whereNotNull('batch_id')->value('batch_id');
            if ($batchId) {
                DB::table('qa_questions')->where('id', $q->id)->update(['batch_id' => $batchId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('qa_questions', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'status']);
            $table->dropConstrainedForeignId('batch_id');
        });
    }
};
