<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_states', function (Blueprint $table) {
            // Menambahkan kolom monitor_token
            $table->string('monitor_token')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_states', function (Blueprint $table) {
            $table->dropColumn('monitor_token');
        });
    }
};
