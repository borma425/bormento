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

class EcommercePaymentSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Payment Integrations';
    protected static ?string $title = 'Payment Gateway Settings';
    protected static ?string $navigationGroup = 'Settings';
    
    protected static string $view = 'filament.pages.ecommerce-payment-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        
        if ($tenant) {
            $this->form->fill([
                'payment_config' => $tenant->payment_config ?? [],
            ]);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Payment Gateway')
                    ->description('Configure how you receive payments. Leave empty if you only accept Cash on Delivery.')
                    ->schema([
                        Select::make('payment_config.provider')
                            ->label('Payment Provider')
                            ->options([
                                'cashup' => 'CashUp (Default)',
                                'paymob' => 'Paymob',
                                'stripe' => 'Stripe',
                            ]),
                        TextInput::make('payment_config.api_url')
                            ->label('Gateway API URL')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('payment_config.app_id')
                            ->label('App/Merchant ID')
                            ->maxLength(255),
                        TextInput::make('payment_config.api_key')
                            ->label('API Secret Key')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ])->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            $data = $this->form->getState();
            
            // Clean up empty payment config so it falls back to null if no provider is selected
            if (empty($data['payment_config']['provider'])) {
                $data['payment_config'] = null;
            }

            $tenant->update([
                'payment_config' => $data['payment_config'],
            ]);

            Notification::make()
                ->title('Integrations Saved')
                ->success()
                ->send();
        }
    }
}
