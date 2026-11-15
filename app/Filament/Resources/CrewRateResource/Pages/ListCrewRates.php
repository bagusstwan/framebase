<?php

namespace App\Filament\Resources\CrewRateResource\Pages;

use App\Filament\Resources\CrewRateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCrewRates extends ListRecords
{
    protected static string $resource = CrewRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
