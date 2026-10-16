<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Services\Notifications\NotificationService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    /** Semua user login boleh membaca: layar kasir butuh aturan pajak & pembulatan untuk menampilkan total. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->settings->all()]);
    }

    /** Kirim contoh laporan harian ke penerima (3.8). Hanya pemilik. */
    public function testEmail(Request $request, NotificationService $notifications): JsonResponse
    {
        abort_unless($request->user()->role === Role::Owner, 403);
        $sent = $notifications->sendTest();

        return response()->json(['data' => ['sent' => $sent, 'recipients' => $notifications->recipients()]]);
    }

    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $this->settings->update($request->validated());

        return response()->json(['data' => $this->settings->all()]);
    }
}
