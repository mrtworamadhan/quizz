<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // Untuk akses via link/barcode dengan aman
            $table->string('name');
            $table->string('class_name'); // Sesuai kesepakatan: asal kelas
            $table->integer('total_score')->default(0); // Akumulasi nilai otomatis
            $table->timestamp('finished_at')->nullable(); // Menandai jika peserta sudah selesai
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
