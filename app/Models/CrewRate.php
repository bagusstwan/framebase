<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class CrewRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'role',
        'service_id',
        'base_fee',
        'base_hours',
        'extra_hourly_fee',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'base_fee' => 'integer',
        'base_hours' => 'integer',
        'extra_hourly_fee' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (CrewRate $crewRate) {
            $query = static::query()
                ->where('role', $crewRate->role);

            if ($crewRate->service_id !== null) {
                $query->where('service_id', $crewRate->service_id);
            } else {
                $query->whereNull('service_id');
            }

            if ($crewRate->exists) {
                $query->where('id', '!=', $crewRate->id);
            }

            if ($query->exists()) {
                $target = $crewRate->service_id ? 'paket layanan terkait' : 'tarif umum (semua paket)';
                throw ValidationException::withMessages([
                    'role' => "Tarif untuk peran '{$crewRate->role}' pada {$target} sudah terdaftar. Tidak boleh ada duplikasi tarif.",
                ]);
            }
        });
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
