<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Services\Billing\CashRegisterService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Cash Registers
 */
class CashRegisterController extends Controller
{
    public function __construct(private readonly CashRegisterService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CashRegister::class);

        $registers = CashRegister::query()
            ->with('user')
            ->when(request()->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return \App\Http\Resources\Api\CashRegisterResource::collection($registers);
    }

    public function show(CashRegister $cashRegister): Response
    {
        $this->authorize('view', $cashRegister);

        return response(\App\Http\Resources\Api\CashRegisterResource::make($cashRegister->load('user')));
    }

    public function open(Request $request): Response
    {
        $this->authorize('create', CashRegister::class);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'opening_balance_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $register = $this->service->open($request->user(), $validated);

        return response([
            'message' => 'Cash register opened.',
            'data' => \App\Http\Resources\Api\CashRegisterResource::make($register->load('user')),
        ], 201);
    }

    public function close(Request $request, CashRegister $cashRegister): Response
    {
        $this->authorize('update', $cashRegister);

        $validated = $request->validate([
            'actual_balance_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $register = $this->service->close($cashRegister, $validated['actual_balance_cents'] ?? null);

        return response([
            'message' => 'Cash register closed.',
            'data' => \App\Http\Resources\Api\CashRegisterResource::make($register->load('user')),
        ]);
    }

    public function entries(CashRegister $cashRegister): AnonymousResourceCollection
    {
        $this->authorize('view', $cashRegister);

        $entries = $this->service->entries($cashRegister);

        return \App\Http\Resources\Api\CashRegisterEntryResource::collection($entries);
    }
}
