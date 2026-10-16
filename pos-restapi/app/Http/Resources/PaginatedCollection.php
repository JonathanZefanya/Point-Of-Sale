<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Format list sesuai CLAUDE.md §4:
 * { "data": [...], "meta": { "current_page", "per_page", "total", "last_page" } }
 * tanpa blok "links" bawaan Laravel.
 */
class PaginatedCollection extends AnonymousResourceCollection
{
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => [
                'current_page' => $paginated['current_page'],
                'per_page' => (int) $paginated['per_page'],
                'total' => $paginated['total'],
                'last_page' => $paginated['last_page'],
            ],
        ];
    }
}
