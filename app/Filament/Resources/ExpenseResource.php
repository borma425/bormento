<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Filament\Resources\ExpenseResource\RelationManagers;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Financials';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Expense Details')
                    ->schema([
                        Forms\Components\DatePicker::make('expense_date')->required()->default(now()),
                        Forms\Components\Select::make('category')
                            ->options([
                                'advertising' => 'Advertising & Marketing',
                                'shipping' => 'Shipping & Delivery',
                                'rent' => 'Rent',
                                'salaries' => 'Salaries',
                                'utilities' => 'Utilities (Electric/Water)',
                                'supplies' => 'Office Supplies',
                                'packaging' => 'Packaging Materials',
                                'maintenance' => 'Maintenance',
                                'other' => 'Other',
                            ])->required(),
                        Forms\Components\TextInput::make('amount')->numeric()->required()->prefix('EGP'),
                        Forms\Components\Textarea::make('description')->columnSpanFull(),
                        Forms\Components\Textarea::make('note')->label('Private Notes')->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')->date()->sortable(),
                Tables\Columns\BadgeColumn::make('category')
                    ->colors([
                        'primary' => 'advertising',
                        'success' => 'shipping',
                        'warning' => 'rent',
                        'danger' => 'salaries',
                    ]),
                Tables\Columns\TextColumn::make('amount')->money('EGP')->sortable(),
                Tables\Columns\TextColumn::make('description')->limit(30),
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
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
