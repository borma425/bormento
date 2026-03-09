<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Filament\Resources\PurchaseResource\RelationManagers;
use App\Models\Purchase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $navigationGroup = 'Financials & Inventory';
    protected static ?int $navigationSort = 4;

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
                Forms\Components\Section::make('Stock Procurement (Purchases)')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->relationship('product', 'name', fn (Builder $query) => 
                                $query->where('tenant_id', app(\App\Models\Tenant::class)->id ?? null)
                            )
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('size')->nullable(),
                        Forms\Components\TextInput::make('color')->nullable(),
                        Forms\Components\TextInput::make('quantity')->numeric()->required()->minValue(1),
                        Forms\Components\TextInput::make('cost_price')->numeric()->required()->prefix('EGP'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('size')->searchable(),
                Tables\Columns\TextColumn::make('color')->searchable(),
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\TextColumn::make('cost_price')->money('EGP')->sortable(),
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (app()->bound(\App\Models\Tenant::class)) {
            $tenant = app(\App\Models\Tenant::class);
            $query->where('tenant_id', $tenant->id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'edit' => Pages\EditPurchase::route('/{record}/edit'),
        ];
    }
}
