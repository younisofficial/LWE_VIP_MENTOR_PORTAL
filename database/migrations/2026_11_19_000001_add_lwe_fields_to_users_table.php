<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Email becomes optional: many students only have a phone number.
            $table->string('email')->nullable()->change();

            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->string('student_code', 30)->nullable()->unique()->after('phone');
            $table->string('city')->nullable()->after('student_code');
            $table->string('avatar_path')->nullable()->after('city');
            $table->enum('status', ['active', 'suspended'])->default('active')->after('avatar_path');
            $table->boolean('whatsapp_opt_in')->default(true)->after('status');

            // Single-device login
            $table->string('current_session_id')->nullable()->after('remember_token');
            $table->string('locked_device_hash', 64)->nullable()->after('current_session_id');
            $table->timestamp('flagged_at')->nullable()->after('locked_device_hash');
            $table->timestamp('last_login_at')->nullable()->after('flagged_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'student_code', 'city', 'avatar_path', 'status', 'whatsapp_opt_in',
                'current_session_id', 'locked_device_hash', 'flagged_at', 'last_login_at',
            ]);
        });
    }
};
