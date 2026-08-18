<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreExpenseRequest;
use App\Http\Resources\Api\ExpenseResource;
use App\Models\Expense;
use App\Services\Billing\ExpenseService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Expenses
 */
class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Expense::class);

        $expenses = Expense::query()
            ->when(request()->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->latest('expense_date')
            ->paginate(20);

        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request): Response
    {
        $this->authorize('create', Expense::class);

        $expense = $this->service->create($request->validated(), $request->user()->id);

        return response([
            'message' => 'Expense recorded.',
            'data' => ExpenseResource::make($expense),
        ], 201);
    }

    public function show(Expense $expense): Response
    {
        $this->authorize('view', $expense);

        return response(ExpenseResource::make($expense));
    }
}
