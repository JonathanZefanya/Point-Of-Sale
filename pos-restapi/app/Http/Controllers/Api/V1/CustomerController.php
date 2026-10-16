<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    /** ?search= nama atau nomor HP; &group= */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Customer::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'group' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return CustomerResource::collection($this->customers->paginate($filters));
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create($request->validated());

        return (new CustomerResource($this->customers->withStatsFind($customer->id)))->response()->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        Gate::authorize('view', $customer);

        return new CustomerResource($this->customers->withStatsFind($customer->id));
    }

    public function update(CustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->validated());

        return new CustomerResource($this->customers->withStatsFind($customer->id));
    }

    public function destroy(Customer $customer): Response
    {
        Gate::authorize('delete', $customer);
        $customer->delete(); // soft delete: riwayat transaksi tetap menyimpan pelanggan ini

        return response()->noContent();
    }

    /** Riwayat belanja pelanggan (terbaru dulu), dibatasi outlet yang boleh diakses user. */
    public function sales(Request $request, Customer $customer): AnonymousResourceCollection
    {
        Gate::authorize('view', $customer);

        return SaleResource::collection($customer->sales()
            ->visibleTo($request->user())
            ->where('status', '!=', SaleStatus::Held)
            ->with(['outlet', 'user', 'payments'])
            ->orderByDesc('completed_at')
            ->paginate(min((int) $request->query('per_page', 20), 100)));
    }
}
