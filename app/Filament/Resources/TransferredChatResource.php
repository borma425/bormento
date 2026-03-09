<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferredChatResource\Pages;
use App\Filament\Resources\TransferredChatResource\RelationManagers;
use App\Models\TransferredChat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransferredChatResource extends Resource
{
    protected static ?string $model = TransferredChat::class;

    protected static ?string $navigationGroup = 'AI Support Desk';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Chat Escalation Details')
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')->disabled(),
                        Forms\Components\TextInput::make('platform')->disabled(),
                        Forms\Components\Textarea::make('transfer_reason')->disabled()->columnSpanFull(),
                        Forms\Components\Textarea::make('last_message')->disabled()->columnSpanFull(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending Action',
                                'resolved' => 'Resolved',
                            ])->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')->searchable(),
                Tables\Columns\BadgeColumn::make('platform')
                    ->colors(['primary' => 'messenger', 'danger' => 'instagram']),
                Tables\Columns\TextColumn::make('transfer_reason')->limit(30),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['warning' => 'pending', 'success' => 'resolved']),
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
            'index' => Pages\ListTransferredChats::route('/'),
            'create' => Pages\CreateTransferredChat::route('/create'),
            'edit' => Pages\EditTransferredChat::route('/{record}/edit'),
        ];
    }
}
