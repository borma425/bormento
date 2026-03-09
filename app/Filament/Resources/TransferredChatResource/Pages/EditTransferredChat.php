<?php

namespace App\Filament\Resources\TransferredChatResource\Pages;

use App\Filament\Resources\TransferredChatResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransferredChat extends EditRecord
{
    protected static string $resource = TransferredChatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
