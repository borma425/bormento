<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Filament\Resources\SaleResource\RelationManagers;
use App\Models\Sale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationGroup = 'Financials & Inventory';
    protected static ?int $navigationSort = 5;

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
                Forms\Components\Section::make('Record a Sale')
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
                        Forms\Components\TextInput::make('selling_price')->numeric()->required()->prefix('EGP'),
                        Forms\Components\TextInput::make('cost_price_at_sale')->numeric()->required()->prefix('EGP'),
                        Forms\Components\TextInput::make('profit')->numeric()->required()->prefix('EGP'),
                        Forms\Components\TextInput::make('governorate')->nullable(),
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
                Tables\Columns\TextColumn::make('selling_price')->money('EGP')->sortable(),
                Tables\Columns\TextColumn::make('profit')->money('EGP')->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
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
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}
