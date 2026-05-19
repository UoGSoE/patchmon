<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('notification_email')->nullable()->after('email');
            $table->string('sender_email')->nullable()->after('notification_email');
            $table->timestamp('silenced_until')->nullable()->after('sender_email');
            $table->text('silence_reason')->nullable()->after('silenced_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'notification_email',
                'sender_email',
                'silenced_until',
                'silence_reason',
            ]);
        });
    }
};
