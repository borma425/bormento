<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Facades\Filament;

class ChatTester extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Test AI Agent';
    protected static ?string $title = 'Chat Simulator';
    protected static ?string $navigationGroup = 'Testing';

    protected static string $view = 'filament.pages.chat-tester';

    public ?int $tenantId = null;
    public ?string $tenantName = '';

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        
        if ($tenant) {
            $this->tenantId = $tenant->id;
            $this->tenantName = $tenant->name;
        }
    }
}
