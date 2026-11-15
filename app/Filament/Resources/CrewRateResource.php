<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrewRateResource\Pages;
use App\Models\Crew;
use App\Models\CrewRate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CrewRateResource extends Resource
{
    protected static ?string $model = CrewRate::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Tarif Peran';

    protected static ?string $pluralModelLabel = 'Tarif Peran';

    protected static ?string $modelLabel = 'Tarif Peran';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Ketentuan Tarif Peran')
                    ->description('Tentukan tarif acuan untuk peran kru, baik berlaku umum maupun spesifik per paket layanan.')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Peran Kru')
                            ->options(Crew::getRoles())
                            ->required()
                            ->rules([
                                fn (Forms\Get $get, ?CrewRate $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                    $serviceId = $get('service_id');
                                    $query = CrewRate::where('role', $value);

                                    if ($serviceId) {
                                        $query->where('service_id', $serviceId);
                                    } else {
                                        $query->whereNull('service_id');
                                    }

                                    if ($record) {
                                        $query->where('id', '!=', $record->id);
                                    }

                                    if ($query->exists()) {
                                        $fail('Kombinasi peran dan paket layanan ini sudah terdaftar.');
                                    }
                                },
                            ]),

                        Forms\Components\Select::make('service_id')
                            ->relationship('service', 'name')
                            ->label('Paket Layanan')
                            ->placeholder('Semua Paket (Tarif Umum)')
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->rules([
                                fn (Forms\Get $get, ?CrewRate $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                    $role = $get('role');
                                    if (! $role) {
                                        return;
                                    }
                                    $query = CrewRate::where('role', $role);

                                    if ($value) {
                                        $query->where('service_id', $value);
                                    } else {
                                        $query->whereNull('service_id');
                                    }

                                    if ($record) {
                                        $query->where('id', '!=', $record->id);
                                    }

                                    if ($query->exists()) {
                                        $fail('Kombinasi peran dan paket layanan ini sudah terdaftar.');
                                    }
                                },
                            ])
                            ->helperText('Kosongkan jika tarif ini berlaku umum untuk semua paket layanan.'),

                        Forms\Components\TextInput::make('base_fee')
                            ->label('Fee Dasar')
                            ->numeric()
                            ->prefix('Rp ')
                            ->minValue(0)
                            ->required()
                            ->helperText('Nominal dasar pembayaran untuk peran ini.'),

                        Forms\Components\TextInput::make('base_hours')
                            ->label('Durasi Kerja Basis')
                            ->numeric()
                            ->suffix('Jam')
                            ->minValue(1)
                            ->nullable()
                            ->helperText('Jam kerja yang dicakup fee dasar. Kosongkan jika tarif flat tanpa hitungan jam.'),

                        Forms\Components\TextInput::make('extra_hourly_fee')
                            ->label('Tarif Lembur per Jam')
                            ->numeric()
                            ->prefix('Rp ')
                            ->suffix('/ Jam')
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Tambahan biaya per jam jika durasi kerja melebihi durasi basis.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->helperText('Tarif non-aktif tidak akan digunakan untuk kalkulasi otomatis pemesanan baru.'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Tarif')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Keterangan opsional tentang syarat atau ruang lingkup tarif ini...'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('role')
                    ->label('Peran Kru')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Paket Layanan')
                    ->default('Semua Paket (Umum)')
                    ->badge(fn ($record): bool => $record->service_id === null)
                    ->color(fn ($record): string => $record->service_id ? 'primary' : 'gray')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('base_fee')
                    ->label('Fee Dasar')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('base_hours')
                    ->label('Durasi Basis')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} Jam" : 'Fleksibel / Flat')
                    ->sortable(),

                Tables\Columns\TextColumn::make('extra_hourly_fee')
                    ->label('Lembur / Jam')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Filter Peran')
                    ->options(Crew::getRoles()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->boolean()
                    ->trueLabel('Aktif Saja')
                    ->falseLabel('Non-Aktif Saja'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
                    ->label('Options')
                    ->color('gray')
                    ->icon('heroicon-m-chevron-down')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Rincian Tarif Peran')
                    ->schema([
                        Infolists\Components\TextEntry::make('role')
                            ->label('Peran Kru')
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('service.name')
                            ->label('Paket Layanan')
                            ->default('Semua Paket (Umum)'),

                        Infolists\Components\TextEntry::make('base_fee')
                            ->label('Fee Dasar')
                            ->money('IDR', locale: 'id'),

                        Infolists\Components\TextEntry::make('base_hours')
                            ->label('Durasi Basis')
                            ->formatStateUsing(fn ($state) => $state ? "{$state} Jam" : 'Fleksibel / Flat'),

                        Infolists\Components\TextEntry::make('extra_hourly_fee')
                            ->label('Tarif Lembur per Jam')
                            ->money('IDR', locale: 'id'),

                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Status Aktif')
                            ->boolean(),

                        Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan Tarif')
                            ->placeholder('Tidak ada catatan khusus')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrewRates::route('/'),
            'create' => Pages\CreateCrewRate::route('/create'),
            'edit' => Pages\EditCrewRate::route('/{record}/edit'),
        ];
    }
}
