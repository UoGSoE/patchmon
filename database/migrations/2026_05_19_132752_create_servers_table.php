<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('cron_expression')->nullable();
            $table->string('schedule_interval')->nullable();
            $table->unsignedInteger('schedule_frequency')->default(1);
            $table->unsignedInteger('grace_value');
            $table->string('grace_units');
            $table->uuid('patch_token')->unique();
            $table->string('notification_email')->nullable();
            $table->string('sender_email')->nullable();
            $table->timestamp('last_patched_at')->nullable();
            $table->timestamp('alerting_since')->nullable();
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamp('silenced_until')->nullable();
            $table->text('silence_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
