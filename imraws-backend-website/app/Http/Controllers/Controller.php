<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

abstract class Controller
{
    /**
     * Build the standard pagination envelope consumed by the mobile app.
     *
     * The Flutter clients expect `{data: [...], meta: {...}}` — wrapping
     * a LengthAwarePaginator directly inside `data` double-nests the rows
     * and breaks every list endpoint (see api_service.dart Paginated).
     */
    protected function paginated(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ];
    }
}
