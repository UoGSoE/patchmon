<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patch_events', function (Blueprint $table) {
            $table->index(['server_id', 'patched_at']);
        });
    }

    public function down(): void
    {
        Schema::table('patch_events', function (Blueprint $table) {
            $table->dropIndex(['server_id', 'patched_at']);
        });
    }
};
