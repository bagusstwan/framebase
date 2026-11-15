<?php

namespace App\Filament\Resources\CrewFeeResource\Pages;

use App\Filament\Resources\CrewFeeResource;
use App\Models\BookingCrew;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCrewFees extends ListRecords
{
    protected static string $resource = CrewFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua')
                ->badge(fn () => BookingCrew::count()),

            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('fee_status', BookingCrew::STATUS_DRAFT))
                ->badge(fn () => BookingCrew::where('fee_status', BookingCrew::STATUS_DRAFT)->count())
                ->badgeColor('gray'),

            'approved' => Tab::make('Disetujui')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('fee_status', BookingCrew::STATUS_APPROVED))
                ->badge(fn () => BookingCrew::where('fee_status', BookingCrew::STATUS_APPROVED)->count())
                ->badgeColor('warning'),

            'paid' => Tab::make('Sudah Dibayar')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('fee_status', BookingCrew::STATUS_PAID))
                ->badge(fn () => BookingCrew::where('fee_status', BookingCrew::STATUS_PAID)->count())
                ->badgeColor('success'),
        ];
    }
}
