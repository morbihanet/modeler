<?php

namespace Morbihanet\Modeler;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    private function successResponse($data, int $code = 200)
    {
        return response()->json($data, $code);
    }

    protected function errorResponse($message, int $code)
    {
        return response()->json(['error' => $message, 'code' => $code], $code);
    }

    protected function showAll(Iterator $collection, int $perPage = 15, int $code = 200)
    {
        if ($collection->isEmpty()) {
            return $this->successResponse(['data' => []], $code);
        }

        return $this->successResponse([
            'data' => $this->cacheResponse($this->paginate($this->sortData($this->filterData($collection)), $perPage))
        ], $code);
    }

    protected function showOne(Item $instance, int $code = 200)
    {
        return $this->successResponse(['data' => $instance->toArray()], $code);
    }

    protected function showMessage($message, int $code = 200)
    {
        return $this->successResponse(['data' => $message], $code);
    }

    protected function filterData(Iterator $collection)
    {
        foreach (request()->query() as $attribute => $value) {
            if (isset($attribute, $value) && is_string($attribute)) {
                $collection = $collection->where($attribute, $value);
            }
        }

        return $collection;
    }

    protected function sortData(Iterator $collection)
    {
        if ($attribute = request()->has('sort_by')) {
            $sortIn = Str::lower(request()->get('sort_in', 'asc'));

            $method = 'asc' === $sortIn ? 'sortBy' : 'sortByDesc';

            $collection = $collection->{$method}($attribute);
        }

        return $collection;
    }

    protected function paginate(Iterator $collection, int $perPage = 15)
    {
        $rules = [
            'per_page' => 'integer|min:2|max:100',
            'page' => 'integer|min:1',
        ];

        Validator::validate(request()->all(), $rules);

        if (request()->has('per_page')) {
            $perPage = (int) request()->per_page;
        }

        if (request()->has('page')) {
            $page = (int) request()->page;
        } else {
            $page = LengthAwarePaginator::resolveCurrentPage();
        }

        $paginated = new LengthAwarePaginator(
            $collection->slice(($page - 1) * $perPage, $perPage)->collect(),
            $collection->count(), $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);

        $paginated->appends(request()->all());

        return $paginated;

    }

    /**
     * @param LengthAwarePaginator|Iterator $collection
     * @return array
     */
    protected function cacheResponse($collection): array
    {
        $data = $collection->toArray();
        $url = request()->url();
        $queryParams = request()->query();

        ksort($queryParams);

        $queryString = http_build_query($queryParams);

        $fullUrl = "{$url}?{$queryString}";

        return Cache::remember($fullUrl, config('modeler.cache_ttl', 1800), function() use ($data) {
            return $data;
        });
    }

    protected function getResourceName(Request $request): string
    {
        $parts = $request->segments();

        $resource = array_pop($parts);

        if (is_numeric($resource)) {
            $resource = array_pop($parts);
        }

        return Str::singular($resource);
    }
}
