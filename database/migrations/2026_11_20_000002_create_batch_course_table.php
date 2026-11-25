<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A batch can now hold several courses.
 * batches.course_id stays as the batch's "main" (first) course so older screens keep working;
 * the full list lives in batch_course.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_course', function (Blueprint $table) {
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->primary(['batch_id', 'course_id']); // no own id column, so "id" is never ambiguous in joins
        });

        // Existing batches: copy their one course into the new table.
        DB::table('batches')->select('id', 'course_id')->orderBy('id')->each(function ($b) {
            if ($b->course_id) {
                DB::table('batch_course')->insertOrIgnore(['batch_id' => $b->id, 'course_id' => $b->course_id]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_course');
    }
};
