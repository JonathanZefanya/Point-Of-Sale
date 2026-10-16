<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\KitchenController;
use App\Http\Controllers\Api\V1\ModifierGroupController;
use App\Http\Controllers\Api\V1\OutletController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductImportController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\ReceiptShareController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\StockOpnameController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\TableController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
| Semua route di file ini otomatis berprefix /api/v1 (lihat bootstrap/app.php).
| Endpoint ditambahkan per task sesuai kontrak di CLAUDE.md §5.
*/

// Publik. Throttle per IP sebagai lapis luar; lockout per akun ada di AuthService.
Route::prefix('auth')->middleware('throttle:30,1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('pin-login', [AuthController::class, 'pinLogin']);
    Route::get('pin-users', [AuthController::class, 'pinUsers']);
});

// Webhook Midtrans (3.5): tanpa login, diverifikasi signature_key
Route::post('payments/midtrans/notification', [PaymentController::class, 'notification'])->middleware('throttle:120,1');

// Struk online untuk link WhatsApp (3.6): tanpa login, wajib tanda tangan valid
Route::get('public/receipts/{sale}', [ReceiptShareController::class, 'show'])
    ->whereNumber('sale')->middleware(['signed:relative', 'throttle:60,1'])->name('receipts.public');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::apiResource('outlets', OutletController::class);

    Route::apiResource('users', UserController::class);
    Route::get('roles', [UserController::class, 'roles']);

    Route::apiResource('categories', CategoryController::class)->except('show');
    Route::apiResource('modifier-groups', ModifierGroupController::class)->except('show');

    // Didaftarkan sebelum apiResource agar "barcode" tidak dianggap {product}
    Route::get('products/barcode/{code}', [ProductController::class, 'barcode'])->where('code', '[A-Za-z0-9._-]+');
    Route::get('products/import/template', [ProductImportController::class, 'template']);
    Route::get('products/export', [ProductImportController::class, 'export']);
    Route::post('products/import', [ProductImportController::class, 'import']);
    Route::apiResource('products', ProductController::class);

    Route::apiResource('promotions', PromotionController::class)->except('show');
    Route::post('vouchers/validate', [PromotionController::class, 'validateVoucher']);

    Route::apiResource('customers', CustomerController::class);
    Route::get('customers/{customer}/sales', [CustomerController::class, 'sales']);

    Route::get('stocks', [StockController::class, 'index']);
    Route::get('stocks/{product}/movements', [StockController::class, 'movements']);
    Route::post('stock-adjustments', [StockController::class, 'adjust']);
    Route::apiResource('stock-opnames', StockOpnameController::class)->except('destroy');
    Route::post('stock-opnames/{stock_opname}/apply', [StockOpnameController::class, 'apply']);
    Route::post('stock-opnames/{stock_opname}/cancel', [StockOpnameController::class, 'cancel']);
    Route::apiResource('stock-transfers', StockTransferController::class)->only(['index', 'store', 'show']);
    Route::post('stock-transfers/{stock_transfer}/receive', [StockTransferController::class, 'receive']);
    Route::post('stock-transfers/{stock_transfer}/cancel', [StockTransferController::class, 'cancel']);

    Route::apiResource('suppliers', SupplierController::class)->except('show');
    Route::apiResource('purchase-orders', PurchaseOrderController::class)->except('destroy');
    Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive']);
    Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel']);

    Route::get('shifts/current', [ShiftController::class, 'current']);
    Route::post('shifts/open', [ShiftController::class, 'open']);
    Route::get('shifts/{shift}', [ShiftController::class, 'show'])->whereNumber('shift');
    Route::post('shifts/{shift}/cash-movements', [ShiftController::class, 'cashMovement']);
    Route::post('shifts/{shift}/close', [ShiftController::class, 'close']);

    // Route statis (held, hold) didaftarkan sebelum {sale}
    Route::get('sales/held', [SaleController::class, 'held']);
    Route::post('sales/hold', [SaleController::class, 'hold']);
    Route::get('payments/gateway', [PaymentController::class, 'gateway']);
    Route::post('payments/qris', [PaymentController::class, 'createQris']);
    Route::get('payments/qris/{intent}', [PaymentController::class, 'showQris'])->whereNumber('intent');

    Route::post('sales/sync', [SaleController::class, 'sync']); // antrian transaksi offline (BE-3.2)
    Route::post('sales/quote', [SaleController::class, 'quote']);
    Route::post('sales/held/{sale}/move', [TableController::class, 'move']);
    Route::post('sales/held/{sale}/merge', [TableController::class, 'merge']);
    Route::apiResource('tables', TableController::class)->except('show');
    Route::delete('sales/held/{sale}', [SaleController::class, 'discardHeld']);
    Route::get('sales', [SaleController::class, 'index']);
    Route::post('sales', [SaleController::class, 'store']);
    Route::get('sales/{sale}', [SaleController::class, 'show'])->whereNumber('sale');
    Route::post('sales/{sale}/void', [SaleController::class, 'void']);
    Route::post('sales/{sale}/refund', [SaleController::class, 'refund']);
    Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt']);
    Route::get('sales/{sale}/share', [ReceiptShareController::class, 'share']);

    Route::get('attendances/current', [AttendanceController::class, 'current']);
    Route::post('attendances/clock-in', [AttendanceController::class, 'clockIn'])->name('attendances.clock-in');
    Route::post('attendances/clock-out', [AttendanceController::class, 'clockOut'])->name('attendances.clock-out');
    Route::get('attendances', [AttendanceController::class, 'index']);

    Route::get('kitchen/tickets', [KitchenController::class, 'index']);
    Route::post('kitchen/tickets/{ticket}/status', [KitchenController::class, 'status']);

    Route::post('settings/test-email', [SettingController::class, 'testEmail']);

    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/filters', [AuditLogController::class, 'filters']);

    Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
    Route::get('reports/sales', [ReportController::class, 'sales']);
    Route::get('reports/payments', [ReportController::class, 'payments']);
    Route::get('reports/{type}', [ReportController::class, 'detail'])->whereIn('type', array_keys(ReportController::DETAIL_REPORTS));

    Route::get('settings', [SettingController::class, 'index']);
    Route::put('settings', [SettingController::class, 'update']);
});
