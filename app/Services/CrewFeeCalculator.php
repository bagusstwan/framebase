<?php

namespace App\Services;

use App\Models\CrewRate;

class CrewFeeCalculator
{
    /**
     * Hitung fee berdasarkan tarif peran dan durasi jam kerja.
     *
     * Rumus:
     * - Jika tarif null -> null (belum diatur)
     * - Jika base_hours null atau durasi null/<= 0 -> base_fee
     * - Selain itu -> base_fee + max(0, durasi - base_hours) * extra_hourly_fee, dibulatkan ke rupiah terdekat
     */
    public static function calculate(?CrewRate $rate, ?float $hours): ?int
    {
        if ($rate === null) {
            return null;
        }

        $baseFee = (int) $rate->base_fee;

        if ($rate->base_hours === null || $hours === null || $hours <= 0) {
            return $baseFee;
        }

        $baseHours = (float) $rate->base_hours;
        $extraHours = max(0.0, (float) $hours - $baseHours);
        $extraHourlyFee = (float) ($rate->extra_hourly_fee ?? 0);

        return (int) round($baseFee + ($extraHours * $extraHourlyFee));
    }

    /**
     * Temukan tarif aktif yang cocok untuk peran dan paket layanan tertentu.
     *
     * Aturan prioritas:
     * 1. Tarif aktif spesifik untuk paket layanan (role + service_id)
     * 2. Tarif aktif umum untuk semua paket (role + service_id = null)
     * 3. null jika tidak ditemukan
     */
    public static function findRate(string $role, ?int $serviceId): ?CrewRate
    {
        if ($serviceId) {
            $specificRate = CrewRate::query()
                ->active()
                ->where('role', $role)
                ->where('service_id', $serviceId)
                ->first();

            if ($specificRate) {
                return $specificRate;
            }
        }

        return CrewRate::query()
            ->active()
            ->where('role', $role)
            ->whereNull('service_id')
            ->first();
    }
}
