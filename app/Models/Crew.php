<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crew extends Model
{
    use HasFactory;

    public const ROLES = [
        'Lead Photographer' => 'Lead Photographer',
        'Assistant Photographer' => 'Assistant Photographer',
        'Videographer Cinematic' => 'Videographer Cinematic',
        'Drone Pilot' => 'Drone Pilot Aerial',
        'Editor Visual' => 'Editor Visual Pasca Produksi',
    ];

    protected $fillable = [
        'name',
        'role',
        'phone',
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function getRoles(): array
    {
        return self::ROLES;
    }

    // Mengatur hubungan timbal balik ke model booking
    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class);
    }

    // Mengatur relasi ke penugasan dan fee rekan
    public function crewAssignments(): HasMany
    {
        return $this->hasMany(BookingCrew::class);
    }
}
