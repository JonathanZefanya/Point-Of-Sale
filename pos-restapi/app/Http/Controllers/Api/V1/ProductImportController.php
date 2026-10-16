<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ImportProductRequest;
use App\Http\Requests\Product\IndexProductRequest;
use App\Models\Product;
use App\Services\Products\ProductImportService;
use App\Services\Products\ProductSpreadsheet;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Import/export produk Excel (2.10). */
class ProductImportController extends Controller
{
    public function __construct(private readonly ProductSpreadsheet $sheet) {}

    public function template(): BinaryFileResponse
    {
        Gate::authorize('create', Product::class);

        return $this->sheet->download('template-import-produk.xlsx', [
            ['Kopi Susu', 'Minuman', '', '', 18000, 9000, 'gelas', 5, 'ya', 20],
            ['Roti Bakar Coklat', 'Makanan', 'RTI-001', '8991234567890', 15000, 7000, 'pcs', 0, 'ya', 10],
        ]);
    }

    /** Filter sama dengan daftar produk (search, category_id, is_active). */
    public function export(IndexProductRequest $request, ProductService $products): BinaryFileResponse
    {
        Gate::authorize('create', Product::class);
        $rows = $products->paginate([...$request->validated(), 'per_page' => 100000, 'page' => 1])->getCollection()
            ->map(fn (Product $p) => [
                $p->name, $p->category?->name, $p->sku, $p->barcode, $p->sell_price, $p->cost_price,
                $p->unit, $p->min_stock, $p->track_stock ? 'ya' : 'tidak', null,
            ])->all();

        return $this->sheet->download('produk-'.now()->format('Ymd').'.xlsx', $rows);
    }

    public function import(ImportProductRequest $request, ProductImportService $import): JsonResponse
    {
        $raw = $this->sheet->read($request->file('file'));

        if ($request->boolean('preview')) {
            return response()->json(['data' => $import->preview($raw)]);
        }

        return response()->json(['data' => ['summary' => $import->commit($raw, (int) $request->validated('outlet_id'))]], 201);
    }
}
