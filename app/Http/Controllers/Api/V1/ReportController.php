<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends ApiController
{
    /** GET /api/v1/reports/payments — the PSP's own payment registry. */
    public function payments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pending,completed,cancelled,refunded'],
        ]);

        $query = Transaction::withoutGlobalScopes()
            ->where('psp_id', $request->user()->id)
            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '>=', $v))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '<=', $v))
            ->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('id');

        $page = $query->paginate(min((int) $request->integer('per_page', 50), 200));

        return $this->ok([
            'items' => TransactionResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
