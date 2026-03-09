<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Sales & Operations';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $tenant = app()->bound(\App\Models\Tenant::class) ? app(\App\Models\Tenant::class) : null;
        if ($tenant && $tenant->business_type === 'clinic') {
            return false;
        }
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Customer Details')
                    ->schema([
                        Forms\Components\TextInput::make('tracking_id')->required()->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('customer_name')->required(),
                        Forms\Components\TextInput::make('customer_address')->required(),
                        Forms\Components\TextInput::make('governorate')->required(),
                        Forms\Components\TagsInput::make('customer_numbers')->label('Phone Numbers'),
                    ])->columns(2),

                Forms\Components\Section::make('Order Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->schema([
                                Forms\Components\TextInput::make('product_name')->required(),
                                Forms\Components\TextInput::make('variant')->label('Color/Size'),
                                Forms\Components\TextInput::make('quantity')->numeric()->required()->default(1),
                                Forms\Components\TextInput::make('price')->numeric()->required(),
                            ])->columns(4)
                    ]),

                Forms\Components\Section::make('Financials & Status')
                    ->schema([
                        Forms\Components\TextInput::make('delivery_fees')->numeric()->default(0)->prefix('EGP'),
                        Forms\Components\TextInput::make('total_amount')->numeric()->required()->prefix('EGP'),
                        Forms\Components\Select::make('payment_method')
                            ->options(['cod' => 'Cash on Delivery', 'cashup' => 'Delivery Fees Only (Cashup)'])->default('cod'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'accepted' => 'Accepted',
                                'delivery_fees_paid' => 'Delivery Fees Paid',
                                'shipped' => 'Shipped',
                                'cancelled' => 'Cancelled',
                            ])->default('pending')->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tracking_id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer_name')->searchable(),
                Tables\Columns\TextColumn::make('governorate')->searchable(),
                Tables\Columns\TextColumn::make('total_amount')->money('EGP')->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'primary' => 'accepted',
                        'info' => 'delivery_fees_paid',
                        'success' => 'shipped',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (app()->bound(\App\Models\Tenant::class)) {
            $tenant = app(\App\Models\Tenant::class);
            $query->where('tenant_id', $tenant->id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
