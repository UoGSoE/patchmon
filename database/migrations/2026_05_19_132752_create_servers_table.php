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
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('netbox_id')->nullable();
            $table->boolean('is_virtual')->default(false);
            $table->timestamp('inactive_since')->nullable();
            $table->unique(['netbox_id', 'is_virtual']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('os_type');
            $table->unsignedInteger('interval_months');
            $table->unsignedInteger('grace_value');
            $table->string('grace_units');
            $table->uuid('patch_token')->unique();
            $table->string('notification_email')->nullable();
            $table->string('sender_email')->nullable();
            $table->timestamp('last_patched_at')->nullable();
            $table->timestamp('alerting_since')->nullable();
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamp('silenced_from')->nullable();
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
