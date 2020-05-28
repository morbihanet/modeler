<?php
namespace Morbihanet\Modeler;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\HeaderBag;

class Api
{
    protected static ?Record $data = null;
    protected static ?Request $request = null;

    public static function call(Request $request, array $data = []): JsonResponse
    {
        if ($request->method() === 'POST') {
            $class = static::class;

            static::$data = Record::make(array_merge($request->all(), $data));
            $action = static::$data->getAction();

            $event = Event::hook($class . '.' . $action, static::$data, $request);

            if ($event instanceof JsonResponse) {
                return $event;
            }

            $swap = Swap::call($class . '@' . $action, static::$data, $request);

            if ($swap instanceof JsonResponse) {
                return $swap;
            }

            static::$request = $request;

            if (in_array($action, get_class_methods($class))) {
                return static::{$action}();
            }
        }

        return static::isError();
    }

    public static function __callStatic(string $name, array $arguments): JsonResponse
    {
        $data = array_shift($arguments);
        $data = $data ?? [];

        $data['action'] = $name;

        return static::call(request(), $data);
    }

    public static function build(array $methods): void
    {
        Swap::in(static::class, $methods);
    }

    public static function events(array $methods): void
    {
        Event::in(static::class, $methods);
    }

    public static function event(string $event, callable $callable): void
    {
        Event::in(static::class, [$event => $callable]);
    }

    public static function action(string $action, callable $callable): void
    {
        Swap::in(static::class, [$action => $callable]);
    }

    /**
     * @param array $data
     * @param int $status
     * @param  HeaderBag|array  $headers
     * @return JsonResponse
     */
    public static function response(array $data, int $status = 200, $headers = []): JsonResponse
    {
        $response = response();

        if (!empty($headers)) {
            $response->withHeaders($headers);
        }

        return $response->json($data, $status);
    }

    public static function forbidden(?array $data = null): JsonResponse
    {
        $data = $data ?? ['message' => 'This action is forbidden'];

        return static::response($data, 403);
    }

    public static function isError(?array $data = null): JsonResponse
    {
        $data = $data ?? ['message' => 'An error occured'];

        return static::response($data, 500);
    }

    public static function notFound(?array $data = null): JsonResponse
    {
        $data = $data ?? ['message' => 'Resource not found'];

        return static::response($data, 404);
    }
}
