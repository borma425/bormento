<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                Forms\Components\Section::make('Product Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU / Code')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->prefix('EGP'),
                        Forms\Components\TextInput::make('discounted_price')
                            ->numeric()
                            ->prefix('EGP'),
                        Forms\Components\TextInput::make('currency')
                            ->default('EGP')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Store Media Gallery')
                    ->description('Select or upload photos and videos for this product. These are shared across all variants unless overridden.')
                    ->schema([
                        \Awcodes\Curator\Components\Forms\CuratorPicker::make('media')
                            ->label('Product Image/Video Gallery')
                            ->multiple()
                            ->buttonLabel('Browse Media Library')
                            ->color('success')
                            ->columnSpanFull()
                            ->helperText('Manage all product assets in your isolated Tenant Library.'),
                    ]),

                Forms\Components\Section::make('Advanced Variants (Sizes, Colors, etc.)')
                    ->description('Define deep variants. You can assign specific stock, extra fees, and modern media (Photos/Videos) to each variant independently.')
                    ->schema([
                        Forms\Components\Repeater::make('attributes')
                            ->label('Product Variants')
                            ->schema([
                                Forms\Components\TextInput::make('variant_name')
                                    ->label('Variant Name (e.g. Red - Size L)')
                                    ->required()
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('stock')
                                    ->label('Available Stock')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('price_modifier')
                                    ->label('Extra Price (Optional)')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('+ EGP')
                                    ->columnSpan(1),
                                \Awcodes\Curator\Components\Forms\CuratorPicker::make('media')
                                    ->label('Variant Specific Photos/Videos')
                                    ->multiple()
                                    ->buttonLabel('Choose from Media Library')
                                    ->color('info')
                                    ->columnSpanFull()
                                    ->helperText('Select or upload media specifically for this variant from your isolated Tenant Library.'),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->collapsible()
                            ->reorderableWithButtons()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sku')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->money(fn ($record) => $record->currency ?? 'EGP')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
