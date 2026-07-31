<?php

declare(strict_types=1);

namespace App\Livewire\Merchant;

use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('merchant.layout')]
class Analytics extends Component
{
    #[Url]
    public string $period = 'month';

    public function setPeriod(string $p): void
    {
        if (in_array($p, ['month', 'quarter', 'year'], true)) {
            $this->period = $p;
        }
    }

    public function render()
    {
        // Demo datasets (UI showcase) in millions of som → tiyin.
        $data = [
            'month' => [
                'labels' => ['Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                'trend' => [210, 640, 380, 300, 260, 240, 320, 410, 380, 520, 610, 570],
                'bars' => [420, 480, 360, 300, 280, 260, 340, 430, 400, 540, 620, 590],
            ],
            'quarter' => [
                'labels' => ['Q4 ’24', 'Q1 ’25', 'Q2 ’25', 'Q3 ’25', 'Q4 ’25', 'Q1 ’26'],
                'trend' => [820, 940, 760, 1180, 1320, 1510],
                'bars' => [820, 940, 760, 1180, 1320, 1510],
            ],
            'year' => [
                'labels' => ['2023', '2024', '2025', '2026'],
                'trend' => [2100, 2680, 3540, 4310],
                'bars' => [2100, 2680, 3540, 4310],
            ],
        ][$this->period];

        $toPts = fn (array $labels, array $vals) => array_map(
            fn ($l, $v) => ['label' => $l, 'value' => Money::toTiyin($v * 1_000_000)],
            $labels, $vals,
        );

        return view('livewire.merchant.analytics', [
            'trend' => $toPts($data['labels'], $data['trend']),
            'bars' => $toPts($data['labels'], $data['bars']),
            'channels' => [
                ['label' => 'ClickPay', 'value' => 48, 'hex' => 'var(--viz-1)'],
                ['label' => 'Payme', 'value' => 32, 'hex' => 'var(--viz-2)'],
                ['label' => 'Uzum', 'value' => 20, 'hex' => 'var(--viz-3)'],
            ],
            'rateSpark' => [78, 80, 79, 82, 84, 86],
            'daysSpark' => [8.1, 7.6, 7.4, 6.9, 6.5, 6.2],
            'payerSpark' => [980, 1040, 1090, 1150, 1200, 1240],
        ])->title(__('ext.analytics.title'));
    }
}
