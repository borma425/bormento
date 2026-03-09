<?php

namespace App\Filament\Resources\TransferredChatResource\Pages;

use App\Filament\Resources\TransferredChatResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransferredChats extends ListRecords
{
    protected static string $resource = TransferredChatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
