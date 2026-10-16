<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Semua Resource turunan kelas ini otomatis memakai format paginasi §4
 * saat dipanggil lewat ::collection($paginator).
 */
abstract class BaseResource extends JsonResource
{
    protected static function newCollection($resource)
    {
        return new PaginatedCollection($resource, static::class);
    }
}
