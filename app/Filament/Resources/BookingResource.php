<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Models\Crew;
use App\Services\CrewFeeCalculator;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Pemesanan Jasa';

    protected static ?string $pluralModelLabel = 'Pemesanan Jasa';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withSum('crewAssignments', 'fee_amount')
            ->with(['crewAssignments.crew', 'client', 'service', 'equipment']);
    }

    /**
     * Hitung ulang estimasi fee pada satu baris repeater rekan.
     */
    public static function recalculateRepeaterItemFee(Forms\Set $set, Forms\Get $get, ?string $role = null, ?float $duration = null): void
    {
        $role = $role ?? $get('role');
        $serviceId = $get('../../service_id');
        $rawDuration = $duration ?? $get('duration_hours');
        $parsedDuration = ($rawDuration !== null && $rawDuration !== '') ? (float) $rawDuration : null;

        if (! $role) {
            $set('fee_calculated', null);

            return;
        }

        $rate = CrewFeeCalculator::findRate($role, $serviceId ? (int) $serviceId : null);
        $calculated = CrewFeeCalculator::calculate($rate, $parsedDuration);

        $set('fee_calculated', $calculated);

        if ($calculated !== null) {
            $set('fee_amount', $calculated);
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Hubungan Transaksi')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->relationship('client', 'name')
                            ->label('Pilih Klien')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('service_id')
                            ->relationship('service', 'name')
                            ->label('Pilih Paket Layanan')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                // Saat paket berubah, perbarui kalkulasi seluruh rekan yang sudah ada di repeater
                                $assignments = $get('crewAssignments') ?? [];
                                foreach ($assignments as $uuid => $item) {
                                    $role = $item['role'] ?? null;
                                    $duration = isset($item['duration_hours']) && $item['duration_hours'] !== '' ? (float) $item['duration_hours'] : null;

                                    if ($role) {
                                        $rate = CrewFeeCalculator::findRate($role, $state ? (int) $state : null);
                                        $calculated = CrewFeeCalculator::calculate($rate, $duration);
                                        $set("crewAssignments.{$uuid}.fee_calculated", $calculated);

                                        if ($calculated !== null) {
                                            $set("crewAssignments.{$uuid}.fee_amount", $calculated);
                                        }
                                    }
                                }
                            }),

                        // Memilih inventaris alat yang akan digunakan ke lokasi
                        Forms\Components\Select::make('equipment')
                            ->relationship('equipment', 'name')
                            ->label('Booking Inventaris Alat')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Penugasan Rekan & Fee Pekerjaan')
                    ->description('Tugaskan rekan atau fotografer untuk pesanan ini.')
                    ->schema([
                        Forms\Components\Repeater::make('crewAssignments')
                            ->relationship('crewAssignments')
                            ->label('Daftar Rekan / Kru Bertugas')
                            ->itemLabel(fn (array $state): ?string => isset($state['crew_id']) ? (Crew::find($state['crew_id'])?->name ?? 'Rekan') : 'Rekan Baru')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Select::make('crew_id')
                                            ->label('Pilih Rekan')
                                            ->options(fn () => Crew::query()->where('is_active', true)->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                if (! $state) {
                                                    return;
                                                }
                                                $crew = Crew::find($state);
                                                if ($crew && $crew->role) {
                                                    $set('role', $crew->role);
                                                    static::recalculateRepeaterItemFee($set, $get, role: $crew->role);
                                                }
                                            }),

                                        Forms\Components\Select::make('role')
                                            ->label('Peran yang Dijalankan')
                                            ->options(Crew::getRoles())
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                static::recalculateRepeaterItemFee($set, $get, role: $state);
                                            }),

                                        Forms\Components\TextInput::make('duration_hours')
                                            ->label('Durasi Kerja (Jam)')
                                            ->numeric()
                                            ->step(0.5)
                                            ->minValue(0.5)
                                            ->suffix('Jam')
                                            ->placeholder('Masukkan durasi kerja rekan')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                $duration = ($state !== null && $state !== '') ? (float) $state : null;
                                                static::recalculateRepeaterItemFee($set, $get, duration: $duration);
                                            }),
                                    ]),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('fee_calculated')
                                            ->label('Fee Sesuai Tarif Acuan')
                                            ->numeric()
                                            ->prefix('Rp ')
                                            ->readOnly()
                                            ->dehydrated()
                                            ->helperText(function (Forms\Get $get) {
                                                $role = $get('role');
                                                $serviceId = $get('../../service_id');
                                                if (! $role) {
                                                    return 'Pilih rekan atau peran untuk melihat tarif.';
                                                }
                                                $rate = CrewFeeCalculator::findRate($role, $serviceId ? (int) $serviceId : null);
                                                if (! $rate) {
                                                    return 'Belum ada tarif acuan aktif untuk peran ini.';
                                                }
                                                $type = $rate->service_id ? 'Tarif khusus paket' : 'Tarif umum peran';
                                                $base = 'Rp '.number_format($rate->base_fee, 0, ',', '.');
                                                $hours = $rate->base_hours ? " ({$rate->base_hours} jam)" : ' (flat)';

                                                return "{$type}: {$base}{$hours}";
                                            }),

                                        Forms\Components\TextInput::make('fee_amount')
                                            ->label('Fee Disepakati (Final)')
                                            ->numeric()
                                            ->prefix('Rp ')
                                            ->minValue(0)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->suffixAction(
                                                Forms\Components\Actions\Action::make('resetToCalculated')
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->tooltip('Hitung Ulang Sesuai Tarif Acuan')
                                                    ->action(function (Forms\Set $set, Forms\Get $get) {
                                                        static::recalculateRepeaterItemFee($set, $get);
                                                    })
                                            )
                                            ->helperText('Nominal pembayaran disepakati.'),

                                        Forms\Components\Select::make('fee_status')
                                            ->label('Status Fee')
                                            ->options([
                                                'draft' => 'Draft (Direncanakan)',
                                                'approved' => 'Approved (Disetujui)',
                                                'paid' => 'Paid (Sudah Ditransfer)',
                                            ])
                                            ->default('draft')
                                            ->required()
                                            ->disableOptionWhen(function (string $value, Forms\Get $get) {
                                                $bookingStatus = $get('../../status');

                                                return in_array($value, ['approved', 'paid']) && $bookingStatus !== 'completed';
                                            })
                                            ->rules([
                                                fn (Forms\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                                    $bookingStatus = $get('../../status');
                                                    if (in_array($value, ['approved', 'paid']) && $bookingStatus !== 'completed') {
                                                        $fail('Fee hanya dapat disetujui (Approved) atau dibayar (Paid) jika status pemesanan sudah "Completed".');
                                                    }
                                                },
                                            ])
                                            ->helperText(function (Forms\Get $get) {
                                                $bookingStatus = $get('../../status');
                                                if ($bookingStatus !== 'completed') {
                                                    return 'Status fee rekan.';
                                                }

                                                return null;
                                            }),
                                    ]),

                                Forms\Components\Textarea::make('fee_notes')
                                    ->label('Catatan Fee')
                                    ->rows(2)
                                    ->placeholder('Masukkan catatan terkait fee rekan')
                                    ->columnSpanFull(),
                            ])
                            ->addActionLabel('Tambah Penugasan Rekan')
                            ->defaultItems(0)
                            ->collapsible()
                            ->reorderableWithButtons()
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('total_crew_fee_summary')
                            ->label('Total Estimasi Fee Rekan')
                            ->content(function (Forms\Get $get) {
                                $assignments = $get('crewAssignments') ?? [];
                                $total = 0;
                                foreach ($assignments as $item) {
                                    $total += (int) ($item['fee_amount'] ?? 0);
                                }

                                return 'Rp '.number_format($total, 0, ',', '.');
                            }),
                    ]),

                Forms\Components\Section::make('Detail Pelaksanaan')
                    ->schema([
                        Forms\Components\DatePicker::make('booking_date')
                            ->label('Tanggal Acara')
                            ->required(),

                        Forms\Components\Textarea::make('location')
                            ->label('Lokasi Pelaksanaan')
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Khusus'),
                    ])->columns(1),

                Forms\Components\Section::make('Status Keuangan dan Proyek')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status Alur Kerja')
                            ->options([
                                'pending' => 'Pending Menunggu DP',
                                'confirmed' => 'Confirmed Jadwal Terkunci',
                                'ongoing' => 'On Going Pelaksanaan Lapangan',
                                'post_production' => 'Post Production Proses Editing',
                                'completed' => 'Completed Selesai Penyerahan',
                            ])
                            ->default('pending')
                            ->live()
                            ->required(),

                        Forms\Components\Select::make('payment_status')
                            ->label('Status Pembayaran')
                            ->options([
                                'unpaid' => 'Unpaid Belum Bayar',
                                'partial' => 'Partial Sudah DP',
                                'paid' => 'Paid Lunas',
                            ])
                            ->default('unpaid')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Nama Klien')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Paket Layanan')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('booking_date')
                    ->label('Tanggal Acara')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status Kerja')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'confirmed' => 'info',
                        'ongoing' => 'warning',
                        'post_production' => 'primary',
                        'completed' => 'success',
                    }),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Pembayaran')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'danger',
                        'partial' => 'warning',
                        'paid' => 'success',
                    }),

                Tables\Columns\TextColumn::make('crew_assignments_sum_fee_amount')
                    ->label('Total Fee Rekan')
                    ->money('IDR', locale: 'id')
                    ->default(0)
                    ->sortable()
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // Tombol Kustom Follow Up WhatsApp (Versi Enterprise & Detail)
                    Tables\Actions\Action::make('whatsapp_followup')
                        ->label('Follow Up WA')
                        ->icon('heroicon-m-chat-bubble-left-ellipsis')
                        ->color('success')
                        ->url(function (Booking $record) {
                            $phone = $record->client->phone ?? '';

                            // Logika mengubah awalan 0 menjadi 62
                            if (str_starts_with($phone, '0')) {
                                $phone = '62'.substr($phone, 1);
                            }

                            $clientName = $record->client->name ?? 'Bapak/Ibu';
                            $serviceName = $record->service->name ?? 'Layanan Visual';

                            // Mengubah format tanggal menjadi rapi (Contoh: 15 Juni 2026)
                            $bookingDate = Carbon::parse($record->booking_date)->translatedFormat('d F Y');

                            // Menerjemahkan status sistem menjadi bahasa manusia yang sopan
                            $statusMap = [
                                'pending' => 'Menunggu Pembayaran (DP)',
                                'confirmed' => 'Jadwal Terkonfirmasi',
                                'ongoing' => 'Persiapan / Pelaksanaan Lapangan',
                                'post_production' => 'Proses Editing (Post-Production)',
                                'completed' => 'Selesai & Penyerahan File',
                            ];
                            $statusText = $statusMap[$record->status] ?? 'Sedang Diproses';

                            // Meracik kalimat korporat yang detail dan elegan (Versi Teks Aman)
                            $text = "Halo Bapak/Ibu {$clientName}, salam hangat dari FrameBase.\n\n"
                                  ."Perkenalkan, kami dari Tim Operasional. Kami ingin mengonfirmasi bahwa pesanan Anda telah terdaftar di sistem kami dengan rincian sebagai berikut:\n\n"
                                  ."- *Layanan*: {$serviceName}\n"
                                  ."- *Tanggal Acara*: {$bookingDate}\n"
                                  ."- *Status Saat Ini*: {$statusText}\n\n"
                                  ."Untuk memastikan kelancaran jalannya proyek, apakah ada *brief* khusus, referensi visual, atau detail teknis lain yang ingin didiskusikan lebih lanjut bersama tim kami?\n\n"
                                  .'Terima kasih atas kepercayaan Anda. Kami berkomitmen memberikan hasil visual yang maksimal.';

                            return 'https://wa.me/'.$phone.'?text='.urlencode($text);
                        })
                        ->openUrlInNewTab(),

                    Tables\Actions\DeleteAction::make(),
                ])
                    ->label('Options')
                    ->color('gray')
                    ->icon('heroicon-m-chevron-down')
                    ->button(),
            ]);
    }

    // Mengatur tampilan View khusus menggunakan Infolist
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Inti Pesanan')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('client.name')
                                    ->label('Klien Pelanggan')
                                    ->weight('bold')
                                    ->icon('heroicon-m-user'),

                                Infolists\Components\TextEntry::make('service.name')
                                    ->label('Layanan Yang Dipilih')
                                    ->weight('bold')
                                    ->icon('heroicon-m-sparkles'),

                                Infolists\Components\TextEntry::make('booking_date')
                                    ->label('Tanggal Pelaksanaan')
                                    ->date('l, d F Y')
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('service.price')
                                    ->label('Nilai Kontrak')
                                    ->money('IDR', locale: 'id')
                                    ->badge()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('equipment.name')
                                    ->label('Peralatan Yang Dipinjam')
                                    ->badge()
                                    ->color('info')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Infolists\Components\Section::make('Rincian Fee & Penugasan Rekan')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('crewAssignments')
                            ->label('Daftar Penugasan Rekan')
                            ->schema([
                                Infolists\Components\TextEntry::make('crew.name')
                                    ->label('Nama Rekan')
                                    ->weight('bold'),

                                Infolists\Components\TextEntry::make('role')
                                    ->label('Peran')
                                    ->badge()
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('duration_hours')
                                    ->label('Durasi Kerja')
                                    ->formatStateUsing(fn ($state) => $state ? "{$state} Jam" : '-'),

                                Infolists\Components\TextEntry::make('fee_amount')
                                    ->label('Fee Disepakati')
                                    ->money('IDR', locale: 'id')
                                    ->weight('bold')
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('fee_status')
                                    ->label('Status Fee')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'draft' => 'gray',
                                        'approved' => 'warning',
                                        'paid' => 'success',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'draft' => 'Draft',
                                        'approved' => 'Approved',
                                        'paid' => 'Paid',
                                        default => $state,
                                    }),

                                Infolists\Components\TextEntry::make('fee_paid_at')
                                    ->label('Tanggal Dibayar')
                                    ->date('d M Y')
                                    ->placeholder('Belum ditransfer'),

                                Infolists\Components\TextEntry::make('fee_notes')
                                    ->label('Catatan Fee')
                                    ->placeholder('Tidak ada catatan')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('total_crew_fee')
                            ->label('Total Fee Seluruh Rekan')
                            ->state(fn (Booking $record): int => $record->total_crew_fee)
                            ->money('IDR', locale: 'id')
                            ->weight('bold')
                            ->color('primary')
                            ->badge(),
                    ]),

                Infolists\Components\Grid::make(3)
                    ->schema([
                        Infolists\Components\Section::make('Status Logistik')
                            ->schema([
                                Infolists\Components\TextEntry::make('location')
                                    ->label('Lokasi Detail')
                                    ->icon('heroicon-m-map-pin'),

                                Infolists\Components\TextEntry::make('notes')
                                    ->label('Catatan Brief')
                                    ->placeholder('Tidak ada catatan khusus'),
                            ])->columnSpan(2),

                        Infolists\Components\Section::make('Indikator Status')
                            ->schema([
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Proses Kerja')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'gray',
                                        'confirmed' => 'info',
                                        'ongoing' => 'warning',
                                        'post_production' => 'primary',
                                        'completed' => 'success',
                                    }),

                                Infolists\Components\TextEntry::make('payment_status')
                                    ->label('Kondisi Keuangan')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'unpaid' => 'danger',
                                        'partial' => 'warning',
                                        'paid' => 'success',
                                    }),
                            ])->columnSpan(1),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
