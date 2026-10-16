<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleRefund;
use App\Models\Shift;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\User;
use App\Policies\CatalogPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Satu policy untuk dua model katalog (auto-discovery hanya mencari <Model>Policy)
        Gate::policy(Product::class, CatalogPolicy::class);
        Gate::policy(Category::class, CatalogPolicy::class);

        // Nama pendek untuk kolom *_type (stock_movements.reference_type, audit_logs.model_type)
        Relation::morphMap([
            'outlet' => Outlet::class,
            'user' => User::class,
            'category' => Category::class,
            'product' => Product::class,
            'shift' => Shift::class,
            'sale' => Sale::class,
            'refund' => SaleRefund::class,
            'promotion' => Promotion::class,
            'purchase_order' => PurchaseOrder::class,
            'stock_opname' => StockOpname::class,
            'stock_transfer' => StockTransfer::class,
        ]);
    }
}
