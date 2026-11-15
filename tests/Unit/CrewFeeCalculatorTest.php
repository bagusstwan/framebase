<?php

use App\Models\Crew;
use App\Models\CrewRate;
use App\Services\CrewFeeCalculator;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

beforeAll(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('crew_rates', function (Blueprint $table) {
        $table->increments('id');
        $table->string('role');
        $table->unsignedBigInteger('service_id')->nullable();
        $table->unsignedBigInteger('base_fee');
        $table->unsignedSmallInteger('base_hours')->nullable();
        $table->unsignedBigInteger('extra_hourly_fee')->default(0);
        $table->boolean('is_active')->default(true);
        $table->text('notes')->nullable();
        $table->timestamps();
    });
});

beforeEach(function () {
    Capsule::table('crew_rates')->truncate();
});

test('it returns null if rate is null', function () {
    expect(CrewFeeCalculator::calculate(null, 5.0))->toBeNull();
});

test('it returns base_fee if rate has no base_hours (flat rate)', function () {
    $rate = new CrewRate([
        'role' => 'Lead Photographer',
        'base_fee' => 500000,
        'base_hours' => null,
        'extra_hourly_fee' => 75000,
    ]);

    expect(CrewFeeCalculator::calculate($rate, 8.0))->toBe(500000);
    expect(CrewFeeCalculator::calculate($rate, null))->toBe(500000);
});

test('it returns base_fee if hours is null, zero, or negative', function () {
    $rate = new CrewRate([
        'role' => 'Assistant Photographer',
        'base_fee' => 250000,
        'base_hours' => 4,
        'extra_hourly_fee' => 50000,
    ]);

    expect(CrewFeeCalculator::calculate($rate, null))->toBe(250000);
    expect(CrewFeeCalculator::calculate($rate, 0.0))->toBe(250000);
    expect(CrewFeeCalculator::calculate($rate, -2.0))->toBe(250000);
});

test('it returns base_fee when working hours do not exceed base_hours', function () {
    $rate = new CrewRate([
        'role' => 'Videographer Cinematic',
        'base_fee' => 600000,
        'base_hours' => 5,
        'extra_hourly_fee' => 100000,
    ]);

    expect(CrewFeeCalculator::calculate($rate, 3.0))->toBe(600000);
    expect(CrewFeeCalculator::calculate($rate, 5.0))->toBe(600000);
});

test('it calculates extra fee proportionally when working hours exceed base_hours', function () {
    $rate = new CrewRate([
        'role' => 'Videographer Cinematic',
        'base_fee' => 600000,
        'base_hours' => 5,
        'extra_hourly_fee' => 100000,
    ]);

    expect(CrewFeeCalculator::calculate($rate, 7.0))->toBe(800000);
    expect(CrewFeeCalculator::calculate($rate, 5.5))->toBe(650000);
});

test('it rounds fractional calculations to the nearest integer rupiah', function () {
    $rate = new CrewRate([
        'role' => 'Drone Pilot',
        'base_fee' => 750000,
        'base_hours' => 3,
        'extra_hourly_fee' => 125555,
    ]);

    $result = CrewFeeCalculator::calculate($rate, 4.33);
    expect($result)->toBe(916988);
});

test('it handles zero extra_hourly_fee gracefully', function () {
    $rate = new CrewRate([
        'role' => 'Editor Visual',
        'base_fee' => 400000,
        'base_hours' => 4,
        'extra_hourly_fee' => 0,
    ]);

    expect(CrewFeeCalculator::calculate($rate, 8.0))->toBe(400000);
});

test('findRate prioritizes specific active package rate over general rate using real Crew roles', function () {
    $roleLead = Crew::ROLES['Lead Photographer'];

    // 1. Buat tarif umum untuk Lead Photographer
    CrewRate::create([
        'role' => $roleLead,
        'service_id' => null,
        'base_fee' => 500000,
        'base_hours' => 4,
        'extra_hourly_fee' => 75000,
        'is_active' => true,
    ]);

    // 2. Buat tarif khusus paket 10 untuk Lead Photographer
    $specificRate = CrewRate::create([
        'role' => $roleLead,
        'service_id' => 10,
        'base_fee' => 850000,
        'base_hours' => 6,
        'extra_hourly_fee' => 100000,
        'is_active' => true,
    ]);

    // 3. Buat tarif khusus paket 20 tetapi non-aktif
    CrewRate::create([
        'role' => $roleLead,
        'service_id' => 20,
        'base_fee' => 999999,
        'base_hours' => 6,
        'extra_hourly_fee' => 100000,
        'is_active' => false,
    ]);

    // Uji skenario paket 10 -> harus mengambil tarif spesifik paket 10
    $found10 = CrewFeeCalculator::findRate($roleLead, 10);
    expect($found10)->not->toBeNull()
        ->and($found10->id)->toBe($specificRate->id)
        ->and($found10->base_fee)->toBe(850000);

    // Uji skenario paket 20 (non-aktif) -> fallback ke tarif umum (500000)
    $found20 = CrewFeeCalculator::findRate($roleLead, 20);
    expect($found20)->not->toBeNull()
        ->and($found20->service_id)->toBeNull()
        ->and($found20->base_fee)->toBe(500000);

    // Uji skenario paket yang tidak punya tarif khusus -> fallback ke tarif umum
    $foundGeneral = CrewFeeCalculator::findRate($roleLead, 999);
    expect($foundGeneral)->not->toBeNull()
        ->and($foundGeneral->service_id)->toBeNull()
        ->and($foundGeneral->base_fee)->toBe(500000);

    // Uji skenario peran yang belum diatur tarifnya -> return null
    $foundUnconfigured = CrewFeeCalculator::findRate(Crew::ROLES['Drone Pilot'], 10);
    expect($foundUnconfigured)->toBeNull();
});
