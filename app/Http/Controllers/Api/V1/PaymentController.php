<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\CheckPayment;
use App\Actions\Payments\ConfirmPayment;
use App\Http\Requests\Api\CheckPaymentRequest;
use App\Http\Requests\Api\ConfirmPaymentRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends ApiController
{
    /** POST /api/v1/payments/check */
    public function check(CheckPaymentRequest $request, CheckPayment $action): JsonResponse
    {
        $result = $action->handle(
            merchantId: (int) $request->integer('institution_id'),
            studentIdNumber: (string) $request->string('student_id_number'),
            amountTiyin: $request->filled('amount') ? (int) $request->integer('amount') : null,
        );

        return $this->ok([
            'check_id' => $result['check_id'],
            'student' => [
                'name' => $result['student']->fullName(),
                'student_id_number' => $result['student']->student_id_number,
            ],
            'amount_owed' => $result['amount_owed'], // tiyin
            'currency' => 'UZS',
            'expires_at' => $result['expires_at'],
        ]);
    }

    /** POST /api/v1/payments/confirm  (requires Idempotency-Key) */
    public function confirm(ConfirmPaymentRequest $request, ConfirmPayment $action): JsonResponse
    {
        $psp = $request->user(); // Psp (api guard)

        $txn = $action->handle(
            pspId: $psp->id,
            checkId: (string) $request->string('check_id'),
            partnerTransactionId: (string) $request->string('partner_transaction_id'),
            amountTiyin: (int) $request->integer('amount'),
            idempotencyKey: $request->header('Idempotency-Key'),
            gateway: $request->input('gateway'),
        );

        return $this->ok((new TransactionResource($txn))->resolve($request), 201);
    }

    /** GET /api/v1/payments/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $txn = Transaction::withoutGlobalScopes()
            ->where('psp_id', $request->user()->id)
            ->find($id);

        if (! $txn) {
            return $this->error('not_found', 'Transaction not found.', 404);
        }

        return $this->ok((new TransactionResource($txn))->resolve($request));
    }
}
