<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Loss;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NetProfitStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenantId = app(Tenant::class)->id ?? null;

        if (!$tenantId) {
            return [
                Stat::make('Gross Revenue', 'EGP 0.00'),
                Stat::make('Total Expenses', 'EGP 0.00'),
                Stat::make('Net Profit', 'EGP 0.00'),
            ];
        }

        // 1. Calculate Gross Revenue (Accepted/Shipped Orders & Direct Sales)
        $orderRevenue = Order::where('tenant_id', $tenantId)
            ->whereIn('status', ['accepted', 'delivery_fees_paid', 'shipped'])
            ->sum('total_amount');
            
        $salesRevenue = Sale::where('tenant_id', $tenantId)->sum('selling_price');
        $grossRevenue = $orderRevenue + $salesRevenue;

        // 2. Calculate Expenses & Costs
        $operationalExpenses = Expense::where('tenant_id', $tenantId)->sum('amount');
        $salesBaseCost = Sale::where('tenant_id', $tenantId)->sum('cost_price_at_sale');
        $lossValue = Loss::where('tenant_id', $tenantId)->sum('total_loss_value');
        
        $totalDeductions = $operationalExpenses + $salesBaseCost + $lossValue;

        // 3. Net Profit
        $netProfit = $grossRevenue - $totalDeductions;

        return [
            Stat::make('Gross Revenue', "EGP " . number_format($grossRevenue, 2))
                ->description('Total incoming cashflow')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Total Expenses & Costs', "EGP " . number_format($totalDeductions, 2))
                ->description('Ops, Shipping, Cost of Goods')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
            Stat::make('Net Profit', "EGP " . number_format($netProfit, 2))
                ->description('Final bottom line')
                ->descriptionIcon($netProfit >= 0 ? 'heroicon-m-banknotes' : 'heroicon-m-exclamation-circle')
                ->color($netProfit >= 0 ? 'primary' : 'warning'),
        ];
    }
}
