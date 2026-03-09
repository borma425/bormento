<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LossResource\Pages;
use App\Filament\Resources\LossResource\RelationManagers;
use App\Models\Loss;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LossResource extends Resource
{
    protected static ?string $model = Loss::class;

    protected static ?string $navigationGroup = 'Financials & Inventory';
    protected static ?int $navigationSort = 6;

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
                Forms\Components\Section::make('Record Damaged/Lost Stock')
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
                        Forms\Components\TextInput::make('cost_price_at_loss')->numeric()->required()->prefix('EGP'),
                        Forms\Components\TextInput::make('total_loss_value')->numeric()->required()->prefix('EGP'),
                        Forms\Components\Textarea::make('reason')->nullable()->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('total_loss_value')->money('EGP')->sortable()->color('danger'),
                Tables\Columns\TextColumn::make('reason')->limit(30),
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
            'index' => Pages\ListLosses::route('/'),
            'create' => Pages\CreateLoss::route('/create'),
            'edit' => Pages\EditLoss::route('/{record}/edit'),
        ];
    }
}
