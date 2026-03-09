<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Tenant;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class OrdersChart extends ChartWidget
{
    protected static ?string $heading = 'Orders Overview (Last 30 Days)';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $tenantId = app(Tenant::class)->id ?? null;

        if (!$tenantId) {
            return [
                'datasets' => [
                    [
                        'label' => 'Orders',
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $data = Trend::query(Order::where('tenant_id', $tenantId))
            ->between(
                start: now()->subDays(30),
                end: now(),
            )
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Orders Processed',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
