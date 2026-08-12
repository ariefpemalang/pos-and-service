<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait PaginatesApiQuery
{
    /**
     * @param  list<string>  $searchColumns
     * @param  list<string>  $select
     */
    protected function paginateQuery(
        Request $request,
        Builder $query,
        array $searchColumns,
        array $select,
        int $defaultPerPage = 15,
        int $maxPerPage = 50,
    ): LengthAwarePaginator {
        $perPage = min(max((int) $request->integer('per_page', $defaultPerPage), 1), $maxPerPage);
        $search = trim((string) $request->query('search', ''));

        if ($select !== []) {
            $query->select($select);
        }

        $query->latest('id');

        if ($search !== '' && $searchColumns !== []) {
            $query->where(function (Builder $q) use ($search, $searchColumns) {
                foreach ($searchColumns as $index => $column) {
                    if ($index === 0) {
                        $q->where($column, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                }
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array{current_page:int,last_page:int,per_page:int,total:int,has_more:bool}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }
}
