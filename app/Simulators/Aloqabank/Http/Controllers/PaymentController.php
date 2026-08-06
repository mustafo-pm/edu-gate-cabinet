<?php

declare(strict_types=1);

namespace App\Simulators\Aloqabank\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Simulators\Aloqabank\Models\SimPartner;
use App\Simulators\Aloqabank\Models\SimPayment;
use App\Simulators\Aloqabank\Models\SimService;
use App\Simulators\Aloqabank\Support\BankResponse;
use App\Simulators\Aloqabank\Support\ErrorCode;
use App\Simulators\Aloqabank\Support\MagicAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /** POST /api/v2/payment — payment order by requisites. */
    public function payment(Request $request): JsonResponse|Response
    {
        return $this->create($request, kind: 'payment', required: [
            'orderId', 'receiverName', 'mfoReceiver', 'receiverAccount',
            'purpose', 'serviceId', 'amount', 'comissionAmount',
        ]);
    }

    /** POST /api/v2/paymentBudget — payment order to the budget. */
    public function paymentBudget(Request $request): JsonResponse|Response
    {
        return $this->create($request, kind: 'paymentBudget', required: [
            'orderId', 'receiverAccount', 'innReceiver', 'purposeCode',
            'purpose', 'serviceId', 'amount', 'comissionAmount',
        ]);
    }

    /** GET /api/v2/payment/{orderId} — status of a previously created order. */
    public function status(Request $request, string $orderId): JsonResponse
    {
        /** @var SimPartner $partner */
        $partner = $request->attributes->get('sim_partner');

        $payment = SimPayment::where('partner_id', $partner->id)
            ->where('order_id', $orderId)
            ->first();

        // The docs give 1008 for "could not fetch data" on this resource, and
        // prescribe re-creating the order — which is only safe because an
        // unknown orderId genuinely means nothing was created.
        if (! $payment) {
            return BankResponse::error(ErrorCode::FETCH_FAILED);
        }

        $payment->settleIfDue();

        return BankResponse::success([
            'payment_status' => $payment->payment_status,
            'doc_id' => $payment->doc_id,
        ]);
    }

    /**
     * @param  array<int, string>  $required
     */
    private function create(Request $request, string $kind, array $required): JsonResponse|Response
    {
        /** @var SimPartner $partner */
        $partner = $request->attributes->get('sim_partner');

        foreach ($required as $field) {
            if (blank($request->input($field))) {
                return BankResponse::error(ErrorCode::MISSING_REQUIRED_FIELD);
            }
        }

        $orderId = (string) $request->input('orderId');

        // "Не может содержать символ пробела."
        if (preg_match('/\s/u', $orderId)) {
            return BankResponse::error(ErrorCode::MISSING_REQUIRED_FIELD);
        }

        $service = SimService::where('partner_id', $partner->id)
            ->whereKey($request->input('serviceId'))
            ->first();

        if (! $service) {
            return BankResponse::error(ErrorCode::SERVICE_NOT_FOUND);
        }

        if (! $service->activated) {
            return BankResponse::error(ErrorCode::SERVICE_NOT_CONFIGURED);
        }

        // Card fields are mandatory only for WORKING_WITH_CARD services.
        if ($service->requiresCard()) {
            foreach (['refNumber', 'cardType', 'cardNumber'] as $field) {
                if (blank($request->input($field))) {
                    return BankResponse::error(ErrorCode::MISSING_REQUIRED_FIELD);
                }
            }
        }

        if ($this->hasControlChars((string) $request->input('receiverName'))) {
            return BankResponse::error(ErrorCode::NAME_HAS_CONTROL_CHARS);
        }

        if ($this->hasControlChars((string) $request->input('purpose'))) {
            return BankResponse::error(ErrorCode::PURPOSE_HAS_CONTROL_CHARS);
        }

        $account = (string) $request->input('receiverAccount');

        // Transport-level failures come before any envelope exists.
        if (MagicAccount::isTimeout($account)) {
            sleep((int) config('simulator.aloqabank.timeout_seconds', 30));

            return BankResponse::error(ErrorCode::SYSTEM_ERROR);
        }

        if (MagicAccount::isMalformed($account)) {
            return response('{"status":"success","code":0,"data":{"payment_status":', 200)
                ->header('Content-Type', 'application/json');
        }

        if ($code = MagicAccount::immediateError($account)) {
            return BankResponse::error($code);
        }

        $amount = (int) $request->input('amount');
        $commission = (int) $request->input('comissionAmount', 0);

        if ($amount <= 0) {
            return BankResponse::error(ErrorCode::MISSING_REQUIRED_FIELD);
        }

        // A duplicate orderId is not given a code in the bank's docs. 1111 is
        // used here because its prescribed handling — query /payment/{orderId}
        // rather than retry — is exactly right for a duplicate. CONFIRM WITH
        // THE BANK before relying on the specific number in production code.
        $duplicate = SimPayment::where('partner_id', $partner->id)
            ->where('order_id', $orderId)
            ->exists();

        if ($duplicate) {
            return BankResponse::error(ErrorCode::SYSTEM_ERROR);
        }

        $total = $amount + $commission;

        // The docs define no insufficient-funds code. Rather than invent one,
        // an underfunded order is ACCEPTED and then rejected by the simulated
        // core banking system — "Удален" — which is a documented outcome and
        // forces our client to handle late rejection either way.
        $affordable = $service->balance >= $total;

        $settlesTo = $affordable ? MagicAccount::settlesTo($account) : SimPayment::DELETED;

        $payment = DB::transaction(function () use ($partner, $service, $request, $kind, $orderId, $amount, $commission, $account, $settlesTo, $affordable, $total) {
            if ($affordable) {
                SimService::whereKey($service->id)->decrement('balance', $total);
            }

            return SimPayment::create([
                'partner_id' => $partner->id,
                'service_id' => $service->id,
                'order_id' => $orderId,
                'doc_id' => $this->docId($kind),
                'kind' => $kind,
                'receiver_name' => $request->input('receiverName'),
                'mfo_receiver' => $request->input('mfoReceiver'),
                'receiver_account' => $account,
                'inn_receiver' => $request->input('innReceiver'),
                'purpose_code' => $request->input('purposeCode'),
                'ref_number' => $request->input('refNumber'),
                'card_type' => $request->input('cardType'),
                'card_number' => $request->input('cardNumber'),
                'purpose' => (string) $request->input('purpose'),
                'amount' => $amount,
                'commission_amount' => $commission,
                'debited' => $affordable,
                'payment_status' => SimPayment::ENTERED,
                'execute_after' => now()->addSeconds((int) config('simulator.aloqabank.settle_after_seconds', 10)),
                'settles_to' => $settlesTo,
            ]);
        });

        // Always "Введен" on creation — settlement is asynchronous. A client
        // that treats this response as "paid" is the bug this simulator exists
        // to catch.
        return BankResponse::success([
            'payment_status' => $payment->payment_status,
            'doc_id' => $payment->doc_id,
        ]);
    }

    private function hasControlChars(string $value): bool
    {
        return (bool) preg_match('/[\x00-\x1F\x7F]/u', $value);
    }

    /** Shapes taken from the docs: "1180_1841519885" and "1290084933". */
    private function docId(string $kind): string
    {
        $serial = (string) random_int(1_000_000_000, 9_999_999_999);

        return $kind === 'payment' ? '1180_'.$serial : $serial;
    }
}
