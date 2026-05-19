<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_jobs', function (Blueprint $table) {
            $table->timestamp('last_alerted_at')->nullable()->after('alerting_since');
        });
    }

    public function down(): void
    {
        Schema::table('monitored_jobs', function (Blueprint $table) {
            $table->dropColumn('last_alerted_at');
        });
    }
};
