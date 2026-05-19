<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('notification_email')->nullable()->after('email');
            $table->string('sender_email')->nullable()->after('notification_email');
            $table->boolean('check_ins_require_token')->default(false)->after('sender_email');
            $table->timestamp('silenced_until')->nullable()->after('check_ins_require_token');
            $table->text('silence_reason')->nullable()->after('silenced_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'notification_email',
                'sender_email',
                'check_ins_require_token',
                'silenced_until',
                'silence_reason',
            ]);
        });
    }
};
