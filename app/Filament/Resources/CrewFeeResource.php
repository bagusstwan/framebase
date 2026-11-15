<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrewFeeResource\Pages;
use App\Models\BookingCrew;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CrewFeeResource extends Resource
{
    protected static ?string $model = BookingCrew::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Rekap Fee Rekan';

    protected static ?string $pluralModelLabel = 'Rekap Fee Rekan';

    protected static ?string $modelLabel = 'Fee Rekan';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = BookingCrew::where('fee_status', BookingCrew::STATUS_APPROVED)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['crew', 'booking.client', 'booking.service']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup('crew.name')
            ->groups([
                Tables\Grouping\Group::make('crew.name')
                    ->label('Rekan')
                    ->collapsible(),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('crew.name')
                    ->label('Rekan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (BookingCrew $record): ?string => $record->crew?->role),

                Tables\Columns\TextColumn::make('booking.client.name')
                    ->label('Pemesanan')
                    ->searchable()
                    ->sortable()
                    ->default('-')
                    ->description(fn (BookingCrew $record): string => $record->booking?->booking_date ? 'Acara: '.Carbon::parse($record->booking->booking_date)->translatedFormat('d M Y') : '-'),

                Tables\Columns\TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration_hours')
                    ->label('Durasi')
                    ->numeric(decimalPlaces: 1)
                    ->suffix(' jam')
                    ->placeholder('-')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fee_amount')
                    ->label('Fee Disepakati')
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Total')
                            ->money('IDR', locale: 'id'),
                    ]),

                Tables\Columns\TextColumn::make('fee_status')
                    ->label('Status Fee')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        BookingCrew::STATUS_DRAFT => 'gray',
                        BookingCrew::STATUS_APPROVED => 'warning',
                        BookingCrew::STATUS_PAID => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        BookingCrew::STATUS_DRAFT => 'Draft',
                        BookingCrew::STATUS_APPROVED => 'Disetujui',
                        BookingCrew::STATUS_PAID => 'Sudah Dibayar',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('fee_paid_at')
                    ->label('Dibayar Pada')
                    ->date('d M Y')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fee_notes')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('crew_id')
                    ->relationship('crew', 'name')
                    ->label('Rekan')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('fee_status')
                    ->label('Status Fee')
                    ->options([
                        BookingCrew::STATUS_DRAFT => 'Draft',
                        BookingCrew::STATUS_APPROVED => 'Disetujui',
                        BookingCrew::STATUS_PAID => 'Sudah Dibayar',
                    ]),

                Tables\Filters\Filter::make('booking_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari Tanggal Acara'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Sampai Tanggal Acara'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $q, $date) => $q->whereHas('booking', fn ($b) => $b->whereDate('booking_date', '>=', $date))
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $q, $date) => $q->whereHas('booking', fn ($b) => $b->whereDate('booking_date', '<=', $date))
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'Dari: '.Carbon::parse($data['from'])->translatedFormat('d M Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Sampai: '.Carbon::parse($data['until'])->translatedFormat('d M Y');
                        }

                        return $indicators;
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\Action::make('view_booking')
                    ->label('Lihat Pemesanan')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->url(fn (BookingCrew $record): string => BookingResource::getUrl('edit', ['record' => $record->booking_id]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('mark_as_paid')
                    ->label('Tandai Sudah Dibayar')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->modalHeading('Tandai Fee Rekan Sudah Dibayar')
                    ->modalDescription('Apakah Anda yakin ingin memproses fee rekan yang dipilih menjadi "Sudah Dibayar"? Hanya baris yang berstatus "Disetujui" pada pemesanan yang telah selesai (Completed) yang akan diproses.')
                    ->action(function (Collection $records) {
                        $updated = 0;
                        $skipped = 0;

                        foreach ($records as $record) {
                            if ($record->fee_status === BookingCrew::STATUS_APPROVED && $record->booking?->status === 'completed') {
                                $record->update([
                                    'fee_status' => BookingCrew::STATUS_PAID,
                                    'fee_paid_at' => now(),
                                ]);
                                $updated++;
                            } else {
                                $skipped++;
                            }
                        }

                        if ($updated > 0) {
                            Notification::make()
                                ->title("{$updated} fee rekan berhasil ditandai sudah dibayar.")
                                ->when($skipped > 0, fn ($n) => $n->body("{$skipped} baris dilewati karena belum berstatus 'Disetujui' atau pemesanan terkait belum berstatus 'Completed'."))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Tidak ada fee yang diproses.')
                                ->body('Pastikan baris yang dipilih sudah berstatus "Disetujui" dan pemesanan terkait sudah berstatus "Completed".')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-calculator')
            ->emptyStateHeading('Belum ada data fee rekan')
            ->emptyStateDescription('Fee rekan akan otomatis tercatat setelah Anda menugaskan rekan pada menu Pemesanan Jasa.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrewFees::route('/'),
        ];
    }
}
