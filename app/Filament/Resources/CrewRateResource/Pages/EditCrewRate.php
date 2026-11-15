<?php

namespace App\Filament\Resources\CrewRateResource\Pages;

use App\Filament\Resources\CrewRateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCrewRate extends EditRecord
{
    protected static string $resource = CrewRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
