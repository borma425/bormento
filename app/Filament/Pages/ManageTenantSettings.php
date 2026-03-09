<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Tenant;

class ManageTenantSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'AI & Store Settings';
    protected static ?string $title = 'AI & Store Settings';
    protected static ?int $navigationSort = 10;
    protected static string $view = 'filament.pages.manage-tenant-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = Tenant::first(); // Aggressive fallback for Phase 1 prototype
        
        if ($tenant) {
            $this->form->fill([
                'business_type' => $tenant->business_type ?? 'ecommerce',
                'reply_only_mode' => $tenant->reply_only_mode,
                'shipping_zones' => $tenant->shipping_zones ?? [],
            ]);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('General Store Identity')
                    ->description('Pick your vertical. The AI adapts entirely based on this.')
                    ->schema([
                        Select::make('business_type')
                            ->label('Store / Business Vertical')
                            ->options([
                                'ecommerce' => 'E-Commerce (Products, Cart, Delivery)',
                                'clinic' => 'Clinic / Services (Booking, Consultations)',
                            ])
                            ->required()
                            ->default('ecommerce'),
                    ]),

                Section::make('AI Chatbot Behavior')
                    ->description('Control how the AI interacts with your customers.')
                    ->schema([
                        Toggle::make('reply_only_mode')
                            ->label('Reply-Only Mode (No Checkout)')
                            ->helperText('If enabled, the AI will act purely as a smart assistant answering questions, taking leads, and will NOT ask the customer to checkout or pay.')
                            ->default(false),
                    ]),

                Section::make('Shipping & Delivery Zones')
                    ->description('Define delivery fees per governorate.')
                    ->schema([
                        Repeater::make('shipping_zones')
                            ->label('Compact Fee Grid')
                            ->schema([
                                TextInput::make('governorate')
                                    ->required()
                                    ->placeholder('e.g. Cairo')
                                    ->hiddenLabel(),
                                TextInput::make('fee')
                                    ->numeric()
                                    ->required()
                                    ->prefix('EGP')
                                    ->hiddenLabel(),
                            ])
                            ->columns(2) // Inline compact columns
                            ->defaultItems(0)
                            ->addActionLabel('Add Governorate Profile'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = Tenant::first(); // Strictly pull tenant to ensure save

        if ($tenant) {
            $tenant->update([
                'business_type' => $data['business_type'],
                'reply_only_mode' => $data['reply_only_mode'],
                'shipping_zones' => $data['shipping_zones'],
            ]);

            Notification::make()
                ->success()
                ->title('Settings Saved Successfully')
                ->send();
        } else {
             Notification::make()
                ->danger()
                ->title('Error finding Tenant record')
                ->send();
        }
    }
}
