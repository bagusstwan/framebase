<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCrew extends Model
{
    use HasFactory;

    protected $table = 'booking_crew';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_PAID => 'Sudah Dibayar',
    ];

    protected $fillable = [
        'booking_id',
        'crew_id',
        'role',
        'duration_hours',
        'fee_calculated',
        'fee_amount',
        'fee_status',
        'fee_approved_at',
        'fee_paid_at',
        'fee_notes',
    ];

    protected $casts = [
        'duration_hours' => 'decimal:1',
        'fee_calculated' => 'integer',
        'fee_amount' => 'integer',
        'fee_approved_at' => 'datetime',
        'fee_paid_at' => 'datetime',
    ];

    public static function getStatuses(): array
    {
        return self::STATUSES;
    }

    protected static function booted(): void
    {
        static::saving(function (BookingCrew $model) {
            if ($model->isDirty('fee_status')) {
                if ($model->fee_status === self::STATUS_APPROVED && is_null($model->fee_approved_at)) {
                    $model->fee_approved_at = now();
                }
                if ($model->fee_status === self::STATUS_PAID) {
                    if (is_null($model->fee_approved_at)) {
                        $model->fee_approved_at = now();
                    }
                    if (is_null($model->fee_paid_at)) {
                        $model->fee_paid_at = now();
                    }
                }
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }

    public function getFeeStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->fee_status] ?? ucfirst((string) $this->fee_status);
    }

    public function getFeeStatusColorAttribute(): string
    {
        return match ($this->fee_status) {
            self::STATUS_PAID => 'success',
            self::STATUS_APPROVED => 'warning',
            default => 'gray',
        };
    }
}
