<?php
namespace Morbihanet\Modeler;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Handler
{
    protected array $middlewares = [];

    public function __construct(array $middlewares = [])
    {
        $this->middlewares = $middlewares;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \Exception
     */
    public function process($request): ResponseInterface
    {
        return $this->handle($request);
    }

    /**
     * @param ServerRequestInterface $request
     * @return \GuzzleHttp\Psr7\MessageTrait|Response
     * @throws \Exception
     */
    public function handle($request)
    {
        $middleware = $this->getMiddleware();

        if (is_callable($middleware)) {
            $response = $middleware($request, [$this, 'handle']);

            if (is_string($response)) {
                return new Response(200, [], $response);
            } else if (is_array($response)) {
                return new Response(200, [], json_encode($response, JSON_PRETTY_PRINT));
            }

            return $response;
        } elseif (in_array('handle', get_class_methods($middleware))) {
            return $middleware->handle($request, [$this, 'handle']);
        } elseif (in_array('process', get_class_methods($middleware))) {
            return $middleware->process($request, [$this, 'handle']);
        }

        throw new \Exception("No middleware intercepts request.");
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    private function getMiddleware()
    {
        $middleware = array_shift($this->middlewares);

        if (null !== $middleware) {
            if (is_string($middleware) && class_exists($middleware)) {
                $middleware = app()->make($middleware)
                ;
            }

            return $middleware;
        }

        throw new \Exception("No middleware found.");
    }

    /**
     * @param mixed $middlewares
     * @return Handler
     */
    public function addMiddleware($middleware): Handler
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * @return mixed
     */
    public function popMiddlewares()
    {
        return array_pop($this->middlewares);
    }
}
