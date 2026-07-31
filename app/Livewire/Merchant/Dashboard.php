<?php

declare(strict_types=1);

namespace App\Livewire\Merchant;

use App\Enums\ScheduleStatus;
use App\Enums\StudentStatus;
use App\Enums\TransactionStatus;
use App\Models\PaymentSchedule;
use App\Models\Student;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('merchant.layout')]
class Dashboard extends Component
{
    #[Url]
    public string $period = 'month'; // month | quarter | year

    public function setPeriod(string $p): void
    {
        if (in_array($p, ['month', 'quarter', 'year'], true)) {
            $this->period = $p;
        }
    }

    public function render()
    {
        $buckets = $this->buckets($this->period);
        [$trend, $counts] = $this->aggregate($buckets);

        $collected = array_map(fn ($b) => $b['value'], $trend);
        $now = end($collected) ?: 0;
        $prev = count($collected) > 1 ? $collected[count($collected) - 2] : 0;
        $cNow = end($counts) ?: 0;
        $cPrev = count($counts) > 1 ? $counts[count($counts) - 2] : 0;
        $avgNow = $cNow > 0 ? intdiv((int) $now, (int) $cNow) : 0;
        $avgPrev = $cPrev > 0 ? intdiv((int) $prev, (int) $cPrev) : 0;

        $billed = (int) PaymentSchedule::whereNot('status', ScheduleStatus::Cancelled)->sum('amount');
        $paid = (int) PaymentSchedule::sum('paid_amount');

        return view('livewire.merchant.dashboard', [
            'trend' => $trend,
            'countsSeries' => $counts,
            'collectedSeries' => $collected,
            'collectedNow' => (int) $now,
            'deltaCollected' => $this->pct($now, $prev),
            'txnNow' => (int) $cNow,
            'deltaTxn' => $this->pct($cNow, $cPrev),
            'avgNow' => $avgNow,
            'deltaAvg' => $this->pct($avgNow, $avgPrev),
            'studentCount' => Student::where('status', StudentStatus::Active)->count(),
            'outstanding' => max(0, $billed - $paid),
            'statusSegments' => $this->scheduleStatusSegments(),
            'topOutstanding' => $this->topOutstanding(),
            'recent' => Transaction::with('student')->latest('id')->limit(6)->get(),
        ]);
    }

    /** @return array<int, array{label:string, from:Carbon, to:Carbon}> */
    private function buckets(string $period): array
    {
        $out = [];

        // Normalise to period start FIRST so subtracting never overflows
        // (e.g. Jul 29 − 5 months must be Feb, not Mar 1).
        if ($period === 'year') {
            $base = now()->startOfYear();
            for ($i = 3; $i >= 0; $i--) {
                $y = $base->copy()->subYears($i);
                $out[] = ['label' => $y->format('Y'), 'from' => $y->copy(), 'to' => $y->copy()->endOfYear()];
            }
        } elseif ($period === 'quarter') {
            $base = now()->startOfQuarter();
            for ($i = 5; $i >= 0; $i--) {
                $q = $base->copy()->subQuarters($i);
                $out[] = ['label' => 'Q'.$q->quarter.' ’'.$q->format('y'), 'from' => $q->copy(), 'to' => $q->copy()->endOfQuarter()];
            }
        } else {
            $base = now()->startOfMonth();
            for ($i = 5; $i >= 0; $i--) {
                $mo = $base->copy()->subMonths($i);
                $out[] = ['label' => $mo->translatedFormat('M'), 'from' => $mo->copy(), 'to' => $mo->copy()->endOfMonth()];
            }
        }

        return $out;
    }

    /** @return array{0: array<int,array{label:string,value:int}>, 1: array<int,int>} */
    private function aggregate(array $buckets): array
    {
        $rows = Transaction::query()
            ->where('status', TransactionStatus::Completed)
            ->where('paid_at', '>=', $buckets[0]['from'])
            ->get(['amount', 'paid_at']);

        $trend = [];
        $counts = [];
        foreach ($buckets as $b) {
            $inRange = $rows->filter(fn ($r) => $r->paid_at !== null
                && $r->paid_at->betweenIncluded($b['from'], $b['to']));
            $trend[] = ['label' => $b['label'], 'value' => (int) $inRange->sum('amount')];
            $counts[] = $inRange->count();
        }

        return [$trend, $counts];
    }

    private function pct(int|float $now, int|float $prev): ?float
    {
        return $prev > 0 ? round((($now - $prev) / $prev) * 100, 1) : null;
    }

    private function scheduleStatusSegments(): array
    {
        $counts = PaymentSchedule::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $palette = ['paid' => '#059669', 'partial' => '#0878FF', 'unpaid' => '#D97706', 'overdue' => '#DC2626', 'cancelled' => '#94A3B8'];

        $segments = [];
        foreach ($palette as $status => $hex) {
            if ((int) ($counts[$status] ?? 0) > 0) {
                $segments[] = ['label' => __('cabinet.status.'.$status), 'value' => (int) $counts[$status], 'hex' => $hex];
            }
        }

        return $segments;
    }

    private function topOutstanding(): array
    {
        return PaymentSchedule::query()
            ->with('student')
            ->whereIn('status', [ScheduleStatus::Unpaid->value, ScheduleStatus::Partial->value, ScheduleStatus::Overdue->value])
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => [
                'name' => $rows->first()->student?->fullName() ?? '—',
                'amount' => (int) $rows->sum(fn ($s) => $s->outstanding()),
            ])
            ->sortByDesc('amount')->take(5)->values()->all();
    }
}
