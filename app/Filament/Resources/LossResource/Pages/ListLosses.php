<?php

namespace App\Filament\Resources\LossResource\Pages;

use App\Filament\Resources\LossResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLosses extends ListRecords
{
    protected static string $resource = LossResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
