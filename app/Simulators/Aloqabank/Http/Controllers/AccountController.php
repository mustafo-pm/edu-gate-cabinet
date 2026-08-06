<?php

declare(strict_types=1);

namespace App\Simulators\Aloqabank\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Simulators\Aloqabank\Models\SimPartner;
use App\Simulators\Aloqabank\Models\SimPayment;
use App\Simulators\Aloqabank\Models\SimService;
use App\Simulators\Aloqabank\Support\BankResponse;
use App\Simulators\Aloqabank\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /** GET /api/v2/account/{serviceId}/balance */
    public function balance(Request $request, string $serviceId): JsonResponse
    {
        /** @var SimPartner $partner */
        $partner = $request->attributes->get('sim_partner');

        $service = SimService::where('partner_id', $partner->id)->whereKey($serviceId)->first();

        if (! $service) {
            return BankResponse::error(ErrorCode::SERVICE_NOT_FOUND);
        }

        // Balance comes back as a STRING and the envelope carries no `code` —
        // both straight from the docs. See BankResponse for why we keep it.
        return BankResponse::plain(['balance' => (string) $service->balance]);
    }

    /** POST /api/v2/account/payments — statement. */
    public function payments(Request $request): JsonResponse
    {
        /** @var SimPartner $partner */
        $partner = $request->attributes->get('sim_partner');

        foreach (['type', 'lastId', 'fromDate', 'toDate', 'serviceId'] as $field) {
            if ($request->input($field) === null) {
                return BankResponse::error(ErrorCode::MISSING_REQUIRED_FIELD);
            }
        }

        $service = SimService::where('partner_id', $partner->id)
            ->whereKey($request->input('serviceId'))
            ->first();

        if (! $service) {
            return BankResponse::error(ErrorCode::SERVICE_NOT_FOUND);
        }

        // type: 0 all · 1 incoming only · 2 outgoing only. Everything the
        // simulator creates is outgoing ("Расход"), so type=1 is legitimately
        // empty until incoming rows are seeded.
        $type = (string) $request->input('type');

        $rows = SimPayment::where('partner_id', $partner->id)
            ->where('service_id', $service->id)
            ->where('id', '>', (int) $request->input('lastId', 0))
            ->whereDate('created_at', '>=', $request->input('fromDate'))
            ->whereDate('created_at', '<=', $request->input('toDate'))
            ->orderBy('id')
            ->get()
            ->each(fn (SimPayment $p) => $p->settleIfDue())
            ->when($type === '1', fn ($c) => $c->take(0))
            ->map(fn (SimPayment $p) => [
                'id' => $p->id,
                'docNumber' => (string) $p->id,
                'docDate' => $p->created_at->format('Y-m-d H:i:s'),
                'paymentDate' => $p->created_at->startOfDay()->format('Y-m-d H:i:s'),
                'type' => 'Расход',
                'mfoPayer' => '00401',
                'accountPayer' => $service->account,
                'namePayer' => $partner->name,
                'innPayer' => '307692757',
                'mfoReceiver' => $p->mfo_receiver,
                'accountReceiver' => $p->receiver_account,
                'nameReceiver' => $p->receiver_name,
                'innReceiver' => $p->inn_receiver,
                'amount' => $p->amount,        // integer here, unlike balance
                'purpose' => $p->purpose,
                'status' => $p->payment_status,
            ])
            ->values()
            ->all();

        return BankResponse::plain($rows);
    }
}
