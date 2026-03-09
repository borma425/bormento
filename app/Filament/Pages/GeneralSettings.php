<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;

class GeneralSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'General Settings';
    protected static ?string $title = 'Store General Settings';
    protected static ?string $navigationGroup = 'Settings';
    
    protected static string $view = 'filament.pages.general-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        
        if ($tenant) {
            $this->form->fill($tenant->toArray());
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Store Information')
                    ->description('Basic details about your business.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Store Name')
                            ->required()
                            ->maxLength(255),
                        Select::make('industry')
                            ->label('Industry')
                            ->options([
                                'retail' => 'Clothing & Retail',
                                'electronics' => 'Electronics',
                                'restaurant' => 'Restaurant & F&B',
                                'services' => 'Services',
                                'other' => 'Other',
                            ])
                            ->required(),
                    ])->columns(2),

                Section::make('Social Media Integrations')
                    ->description('Connect your AI agent to your social media pages.')
                    ->schema([
                        TextInput::make('fb_page_id')
                            ->label('Facebook Page ID')
                            ->maxLength(255),
                        TextInput::make('fb_page_token')
                            ->label('Facebook Page Access Token')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        TextInput::make('ig_account_id')
                            ->label('Instagram Account ID')
                            ->maxLength(255),
                        TextInput::make('ig_access_token')
                            ->label('Instagram Access Token')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            $data = $this->form->getState();
            $tenant->update($data);

            Notification::make()
                ->title('Saved successfully')
                ->success()
                ->send();
        }
    }
}
