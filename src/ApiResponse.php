<?php

namespace Morbihanet\Modeler;

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
            return $this->successResponse(['data' => $collection->toArray()], $code);
        }

        $collection = $this->filterData($collection);
        $collection = $this->sortData($collection);
        $collection = $this->paginate($collection,$perPage);

        return $this->successResponse(['data' => $this->cacheResponse($collection)], $code);
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
            $collection = $collection->sortBy->{$attribute};
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

        $results = $collection->slice(($page - 1) * $perPage, $perPage)->toArray();

        $paginated = new LengthAwarePaginator($results, $collection->count(), $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);

        $paginated->appends(request()->all());

        return $paginated;

    }

    protected function transformData($data, $transformer)
    {
        $transformation = fractal($data, new $transformer);

        return $transformation->toArray();
    }

    protected function cacheResponse(Iterator $collection): array
    {
        $data = $collection->toArray();
        $url = request()->url();
        $queryParams = request()->query();

        ksort($queryParams);

        $queryString = http_build_query($queryParams);

        $fullUrl = "{$url}?{$queryString}";

        return Cache::remember($fullUrl, 30/60, function() use ($data) {
            return $data;
        });
    }
}