<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pastikan tabel booking_crew benar-benar ada. Jika tidak ada, jangan lewati diam-diam, melainkan gagalkan dengan jelas.
        if (! Schema::hasTable('booking_crew')) {
            throw new RuntimeException("Tabel 'booking_crew' tidak ditemukan. Pastikan migrasi create_booking_crew_table sudah dijalankan.");
        }

        Schema::table('booking_crew', function (Blueprint $table) {
            if (! Schema::hasColumn('booking_crew', 'role')) {
                // Kolom role dibuat nullable agar kompatibel dan aman untuk data lama
                $table->string('role')->nullable()->after('crew_id');
            }
            if (! Schema::hasColumn('booking_crew', 'duration_hours')) {
                $table->decimal('duration_hours', 5, 1)->nullable()->after('role');
            }
            if (! Schema::hasColumn('booking_crew', 'fee_calculated')) {
                $table->unsignedBigInteger('fee_calculated')->nullable()->after('duration_hours');
            }
            if (! Schema::hasColumn('booking_crew', 'fee_amount')) {
                $table->unsignedBigInteger('fee_amount')->default(0)->after('fee_calculated');
            }
            if (! Schema::hasColumn('booking_crew', 'fee_status')) {
                $table->string('fee_status')->default('draft')->after('fee_amount');
            }
            if (! Schema::hasColumn('booking_crew', 'fee_approved_at')) {
                $table->timestamp('fee_approved_at')->nullable()->after('fee_status');
            }
            if (! Schema::hasColumn('booking_crew', 'fee_paid_at')) {
                $table->timestamp('fee_paid_at')->nullable()->after('fee_approved_at');
            }
            if (! Schema::hasColumn('booking_crew', 'fee_notes')) {
                $table->text('fee_notes')->nullable()->after('fee_paid_at');
            }
        });

        // Backfill role dari crews.role HANYA untuk baris yang belum memiliki role (tidak menimpa data yang sudah terisi)
        if (Schema::hasTable('crews')) {
            $rows = DB::table('booking_crew')
                ->where(function ($query) {
                    $query->whereNull('role')->orWhere('role', '');
                })
                ->get();

            foreach ($rows as $row) {
                $crewRole = DB::table('crews')->where('id', $row->crew_id)->value('role');
                if ($crewRole) {
                    DB::table('booking_crew')->where('id', $row->id)->update(['role' => $crewRole]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('booking_crew')) {
            return;
        }

        Schema::table('booking_crew', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach ([
                'role',
                'duration_hours',
                'fee_calculated',
                'fee_amount',
                'fee_status',
                'fee_approved_at',
                'fee_paid_at',
                'fee_notes',
            ] as $column) {
                if (Schema::hasColumn('booking_crew', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
