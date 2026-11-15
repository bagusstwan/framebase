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
        Schema::create('crew_rates', function (Blueprint $table) {
            $table->id();
            $table->string('role');

            // Relasi ke tabel services menggunakan cascade on delete:
            // Jika paket layanan dihapus oleh admin, tarif khusus yang terikat pada paket tersebut
            // ikut terhapus secara otomatis agar tidak menjadi data yatim (orphaned rate).
            $table->foreignId('service_id')->nullable()->constrained('services')->cascadeOnDelete();

            $table->unsignedBigInteger('base_fee');
            $table->unsignedSmallInteger('base_hours')->nullable();
            $table->unsignedBigInteger('extra_hourly_fee')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['role', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crew_rates');
    }
};
