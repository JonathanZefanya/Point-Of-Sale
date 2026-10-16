<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api/v1/_test')->middleware('api')->group(function () {
            Route::post('/validate', fn (Request $r) => $r->validate(['price' => 'required|integer']));
            Route::get('/conflict', fn () => throw new BusinessException('Stok Kopi Susu tidak cukup. Tersisa 2.'));
            Route::get('/crash', fn () => throw new \RuntimeException('detail internal'));
            Route::get('/private', fn () => 'ok')->middleware('auth:sanctum');
        });
    }

    public function test_validation_error_uses_indonesian_format(): void
    {
        $this->postJson('/api/v1/_test/validate', [])
            ->assertStatus(422)
            ->assertExactJson([
                'message' => 'Data tidak valid.',
                'errors' => ['price' => ['Harga wajib diisi.']],
            ]);
    }

    public function test_business_exception_returns_409_with_message(): void
    {
        $this->getJson('/api/v1/_test/conflict')
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Stok Kopi Susu tidak cukup. Tersisa 2.']);
    }

    public function test_unauthenticated_returns_401_json_without_redirect(): void
    {
        $this->get('/api/v1/_test/private')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Sesi Anda telah berakhir. Silakan login kembali.');
    }

    public function test_unknown_endpoint_returns_404_json(): void
    {
        $this->get('/api/v1/tidak-ada')
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    public function test_server_error_hides_details_when_debug_off(): void
    {
        config(['app.debug' => false]);

        $this->getJson('/api/v1/_test/crash')
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Terjadi kesalahan di server. Coba lagi; jika tetap gagal, hubungi admin.']);
    }

    public function test_cors_allows_frontend_origin(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/_test/validate')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
