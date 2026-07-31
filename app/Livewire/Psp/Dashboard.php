<?php

declare(strict_types=1);

namespace App\Livewire\Psp;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('partner.layout')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $psp = auth('psp')->user()->psp;

        $completed = Transaction::where('status', TransactionStatus::Completed);

        return view('livewire.psp.dashboard', [
            'balance' => $psp->depositBalance(),
            'txnCount' => (clone $completed)->count(),
            'volume' => (int) (clone $completed)->sum('amount'),
            'commission' => (int) (clone $completed)->sum('commission_amount'),
            'trend' => $this->monthlyVolume(),
            'statusSegments' => $this->statusSegments(),
            'recent' => Transaction::with('merchant')->latest('id')->limit(6)->get(),
        ]);
    }

    private function monthlyVolume(): array
    {
        $rows = Transaction::query()
            ->where('status', TransactionStatus::Completed)
            ->where('paid_at', '>=', now()->subMonths(5)->startOfMonth())
            ->get(['amount', 'paid_at']);

        $buckets = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $buckets[$m->format('Y-m')] = ['label' => $m->translatedFormat('M'), 'value' => 0];
        }

        foreach ($rows as $r) {
            $key = Carbon::parse($r->paid_at)->format('Y-m');
            if (isset($buckets[$key])) {
                $buckets[$key]['value'] += (int) $r->amount;
            }
        }

        return array_values($buckets);
    }

    private function statusSegments(): array
    {
        $counts = Transaction::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $palette = [
            'completed' => '#059669',
            'pending' => '#0878FF',
            'refunded' => '#7C3AED',
            'cancelled' => '#DC2626',
        ];

        $segments = [];
        foreach ($palette as $status => $hex) {
            $value = (int) ($counts[$status] ?? 0);
            if ($value > 0) {
                $segments[] = ['label' => __('cabinet.status.'.$status), 'value' => $value, 'hex' => $hex];
            }
        }

        return $segments;
    }
}
