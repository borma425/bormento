<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\Tenant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockAlert extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->where('tenant_id', app(Tenant::class)->id ?? null)
                    // We need to find products where ANY variant stock is < 5.
                    // Since attributes is JSON, we can do a json extracting search,
                    // but for simplicity and performance in Filament, we'll fetch all and filter, 
                    // or use a smart JSON query if supported by MySQL.
                    ->whereJsonContains('attributes', ['stock' => 0])
                    ->orWhereRaw("JSON_SEARCH(attributes, 'all', '1', null, '$[*].stock') IS NOT NULL")
                    ->orWhereRaw("JSON_SEARCH(attributes, 'all', '2', null, '$[*].stock') IS NOT NULL")
                    ->orWhereRaw("JSON_SEARCH(attributes, 'all', '3', null, '$[*].stock') IS NOT NULL")
                    ->orWhereRaw("JSON_SEARCH(attributes, 'all', '4', null, '$[*].stock') IS NOT NULL")
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('low_variants')
                    ->label('Variants Low on Stock')
                    ->getStateUsing(function (Product $record) {
                        if (!is_array($record->attributes)) return 'No Variants Setup';
                        
                        $low = [];
                        foreach ($record->attributes as $variant) {
                            $stock = (int) ($variant['stock'] ?? 0);
                            if ($stock < 5) {
                                $vName = $variant['variant_name'] ?? 'Unknown';
                                $low[] = "{$vName} ({$stock} left)";
                            }
                        }
                        
                        return implode(', ', $low);
                    })
                    ->badge()
                    ->color('danger'),
            ])
            ->heading('Critical Inventory Alerts')
            ->description('These products have variants with fewer than 5 items in stock.');
    }
}
