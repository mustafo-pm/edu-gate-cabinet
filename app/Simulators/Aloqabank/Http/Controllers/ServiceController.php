<?php

declare(strict_types=1);

namespace App\Simulators\Aloqabank\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Simulators\Aloqabank\Models\SimPartner;
use App\Simulators\Aloqabank\Models\SimService;
use App\Simulators\Aloqabank\Support\BankResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /** GET /api/v2/services */
    public function index(Request $request): JsonResponse
    {
        /** @var SimPartner $partner */
        $partner = $request->attributes->get('sim_partner');

        $services = $partner->services()->orderBy('id')->get()
            ->map(fn (SimService $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'activated' => $s->activated,
                'type' => $s->type,
                'account' => $s->account,
            ])
            ->all();

        return BankResponse::success($services);
    }
}
