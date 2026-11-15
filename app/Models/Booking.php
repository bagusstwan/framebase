<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'service_id',
        'booking_date',
        'location',
        'status',
        'payment_status',
        'notes',
    ];

    // Mengatur relasi balik ke model Client
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // Mengatur relasi balik ke model Service
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // Mengatur hubungan penugasan rekan melalui model pivot BookingCrew
    public function crewAssignments(): HasMany
    {
        return $this->hasMany(BookingCrew::class);
    }

    // Mengatur hubungan bahwa satu pemesanan bisa melibatkan banyak kru tim (pivot tetap dipertahankan)
    public function crews(): BelongsToMany
    {
        return $this->belongsToMany(Crew::class)
            ->withPivot([
                'id',
                'role',
                'duration_hours',
                'fee_calculated',
                'fee_amount',
                'fee_status',
                'fee_approved_at',
                'fee_paid_at',
                'fee_notes',
            ])
            ->withTimestamps();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // Mengatur hubungan bahwa satu pemesanan bisa meminjam banyak alat
    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class);
    }

    // Accessor total fee rekan untuk pesanan ini
    public function getTotalCrewFeeAttribute(): int
    {
        if (array_key_exists('crew_assignments_sum_fee_amount', $this->attributes)) {
            return (int) ($this->attributes['crew_assignments_sum_fee_amount'] ?? 0);
        }

        if ($this->relationLoaded('crewAssignments')) {
            return (int) $this->crewAssignments->sum('fee_amount');
        }

        return (int) $this->crewAssignments()->sum('fee_amount');
    }
}
