<?php
namespace Morbihanet\Modeler;

use Exception;
use Illuminate\Support\Str;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;

class Belt
{
    protected array $routes = [];
    protected array $paths = [];
    protected array $resolvers = [];
    protected $namedRoutes = [];
    protected string $basePath = '';

    protected array $matchTypes = [
        'i'     => '[0-9]++',
        'a'     => '[0-9A-Za-z]++',
        'h'     => '[0-9A-Fa-f]++',
        'slug'  => '[a-z0-9]+(?:-[a-z0-9]+)',
        'sha1'  => '[0-9a-f]{40}',
        'md5'   => '[0-9a-f]{32}',
        '*'     => '.+?',
        '**'    => '.++',
        ''      => '[^/\.]++',
    ];

    /** @var Handler */
    protected $handler;

    protected array $params = [];

    /** @var string */
    const METHOD_KEY = '_method';

    public function __construct(array $routes = [], string $basePath = '', array $matchTypes = [])
    {
        $this->handler = new Handler;

        $this->addRoutes($routes)
            ->setBasePath($basePath)
            ->addMatchTypes($matchTypes);
    }

    public static function getInstance(string $basePath = ''): self
    {
        return Core::getOr('routage.' . sha1($basePath), function () use ($basePath) {
            return new static([], $basePath);
        });
    }

    public function inbasePath(string $basePath, callable $next): self
    {
        $actualBasePath = $this->basePath;

        $next($this->setBasePath($actualBasePath . $basePath));

        return $this->setBasePath($actualBasePath);
    }

    public function group(string $prefix, callable $next): self
    {
        $actualPrefix = $this->prefix();

        $this->prefix($actualPrefix . $prefix);

        $next($this);

        $this->prefix($actualPrefix);

        return $this;
    }

    public function prefix(?string $prefix = null): string
    {
        if (null !== $prefix) {
            Core::set('routage.prefix.' . $this->basePath, $prefix);
        }

        return Core::get('routage.prefix.' . $this->basePath, '');
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function addRoutes($routes): self
    {
        if (!is_array($routes) && !$routes instanceof Traversable) {
            throw new Exception('Routes should be an array or an instance of Traversable');
        }

        foreach($routes as $route) {
            call_user_func_array([$this, 'add'], $route);
        }

        return $this;
    }

    public function setBasePath($basePath): self
    {
        $this->basePath = $basePath;

        return $this;
    }

    public function addMatchTypes($matchTypes): self
    {
        $this->matchTypes = array_merge($this->matchTypes, $matchTypes);

        return $this;
    }

    public function get(string $route, $target, ?string $name = null): self
    {
        return $this->add('GET', $route, $target, $name);
    }

    public function post(string $route, $target, ?string $name = null): self
    {
        return $this->add('POST', $route, $target, $name);
    }

    public function getPost(string $route, $target, ?string $name = null): self
    {
        return $this->add('GET|POST', $route, $target, $name);
    }

    public function put(string $route, $target, ?string $name = null): self
    {
        return $this->add('PUT', $route, $target, $name);
    }

    public function patch(string $route, $target, ?string $name = null): self
    {
        return $this->add('PATCH', $route, $target, $name);
    }

    public function options(string $route, $target, ?string $name = null): self
    {
        return $this->add('OPTIONS', $route, $target, $name);
    }

    public function delete(string $route, $target, ?string $name = null): self
    {
        return $this->add('DELETE', $route, $target, $name);
    }

    public function any(string $route, $target, ?string $name = null): self
    {
        return $this->add('GET|POST|PUT|PATCH|DELETE|OPTIONS', $route, $target, $name);
    }

    public function name(string $name): self
    {
        return $this->as($name);
    }

    public function as(string $name): self
    {
        $route = array_pop($this->routes);

        if (is_array($route)) {
            $middleware = null;

            if (count($route) === 5) {
                $middleware = array_pop($route);
            }

            array_pop($route);

            $route[] = $name;

            if (null !== $middleware) {
                $route[] = $middleware;
            }

            $this->paths[$name] = $this->basePath;
            $this->namedRoutes[$name] = $route[1];

            $this->routes[] = $route;
        }

        return $this;
    }

    public function middleware($middleware): self
    {
        $route = array_pop($this->routes);

        if (is_array($route)) {
            if (count($route) === 5) {
                array_pop($route);
            }

            $route[] = $middleware;
            $this->routes[] = $route;
        }

        return $this;
    }

    public function add(string $method, string $route, $target, ?string $name = null): self
    {
        $name = $name ?? sha1(serialize($method.$route));

        $route = $this->prefix() . $route;

        if (isset($route[0]) && $route[0] !== '/' && $route[0] !== '@') {
            $route = '/' . $route;
        }

        $this->routes[] = [$method, $route, $target, $name,];

        if (!empty($name)) {
            if (isset($this->namedRoutes[$name])) {
                throw new Exception("Can not redeclare route '{$name}'");
            } else {
                $this->paths[$name] = $this->basePath;
                $this->namedRoutes[$name] = $route;
            }
        }

        return $this;
    }

    public function route(string $target, array $params = [])
    {
        $url = '';

        foreach ($this->routes as $route) {
            if ($route[2] === $target) {
                $path = $route[1] ?? $this->basePath;
                $url = $path . $route[1];

                if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route[1], $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $index => $match) {
                        [$block, $pre, $type, $param, $optional] = $match;

                        if ($pre) {
                            $block = substr($block, 1);
                        }

                        if(isset($params[$param])) {
                            $url = str_replace($block, $params[$param], $url);
                        } elseif ($optional && $index !== 0) {
                            $url = str_replace($pre . $block, '', $url);
                        } else {
                            $url = str_replace($block, '', $url);
                        }
                    }
                }
            }
        }

        return $url;
    }

    public function url($routeName, array $params = [])
    {
        if (null === ($route = $this->namedRoutes[$routeName] ?? null)) {
            throw new Exception("Route '{$routeName}' does not exist.");
        }

        $path = $this->path[$routeName] ?? $this->basePath;

        $url = $path . $route;

        if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $index => $match) {
                [$block, $pre, $type, $param, $optional] = $match;

                if ($pre) {
                    $block = substr($block, 1);
                }

                if(isset($params[$param])) {
                    $url = str_replace($block, $params[$param], $url);
                } elseif ($optional && $index !== 0) {
                    $url = str_replace($pre . $block, '', $url);
                } else {
                    $url = str_replace($block, '', $url);
                }
            }
        }

        return $url;
    }

    public function resolve(string $key, $resolver): self
    {
        $keys = array_keys($this->paths);
        $name = end($keys);

        $this->resolvers[$name][$key] = $resolver;

        return $this;
    }

    public function find($requestUrl = null, $requestMethod = null)
    {
        $params = [];

        if ($requestUrl === null) {
            $requestUrl = $_SERVER['REQUEST_URI'] ?? '/';
        }

        if ($requestMethod === null) {
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        }

        if (!empty($_POST)) {
            $additionalMethods = ['PUT', 'DELETE', 'PATCH', 'OPTIONS'];

            if (isset($_POST[self::METHOD_KEY]) && in_array($_POST[self::METHOD_KEY], $additionalMethods)) {
                $requestMethod = $_POST[self::METHOD_KEY];
                unset($_POST[self::METHOD_KEY]);
            }
        }

        /** @var array $handler */
        foreach ($this->routes as $handler) {
            $methods = array_shift($handler);
            $route = array_shift($handler);
            $target = array_shift($handler);
            $name = array_shift($handler);
            $middleware = array_shift($handler);

            $method_match = Core::matches($requestMethod, $methods);

            if (false === $method_match) {
                continue;
            }

            $path = $this->basePath;

            if (!empty($name) && isset($this->paths[$name])) {
                $path = $this->paths[$name];
            }

            $requestUrl = substr($requestUrl, strlen($path));

            if (($strpos = strpos($requestUrl, '?')) !== false) {
                $requestUrl = substr($requestUrl, 0, $strpos);
            }

            if ($route === '*') {
                $match = true;
            } elseif (isset($route[0]) && $route[0] === '@') {
                $pattern = '`' . substr($route, 1) . '`u';
                $match = preg_match($pattern, $requestUrl, $params) === 1;
            } elseif (($position = strpos($route, '[')) === false) {
                $match = strcmp($requestUrl, $route) === 0;
            } else {
                if (strncmp($requestUrl, $route, $position) !== 0) {
                    continue;
                }

                $regex = $this->compileRoute($route);
                $match = preg_match($regex, $requestUrl, $params) === 1;
            }

            if ($match) {
                if ($params) {
                    $this->params = [];

                    foreach ($params as $key => $value) {
                        if (is_numeric($key)) {
                            unset($params[$key]);
                        } else {
                            $resolver = $this->resolvers[$name][$key] ?? null;

                            if (is_callable($resolver)) {
                                if ($resolver instanceof Db) {
                                    try {
                                        $value = $resolver->findOrFail($value);
                                    } catch (Exception $e) {
                                        return false;
                                    } catch (\Throwable $e) {
                                        return false;
                                    }
                                } else {
                                    try {
                                        $value = $resolver($value);
                                    } catch (Exception $e) {
                                        return false;
                                    } catch (\Throwable $e) {
                                        return false;
                                    }
                                }
                            } else if (is_string($resolver) && class_exists($resolver)) {
                                try {
                                    $value = app()->make($resolver)->findOrFail($value);
                                } catch (Exception $e) {
                                    return false;
                                } catch (\Throwable $e) {
                                    return false;
                                }
                            }

                            $_REQUEST[$key] = $value;
                            $this->params[] = $value;
                        }
                    }
                }

                return compact('target', 'middleware', 'params', 'name');
            }
        }

        return false;
    }

    public function send(
        ?callable $success = null,
        ?callable $fail = null,
        ?string $requestUrl = null,
        ?string $requestMethod = null
    ) {
        $route = $this->find($requestUrl, $requestMethod);

        if (false !== $route) {
            if (is_callable($success)) {
                return $success($route, $this);
            }
        } else {
            if (is_callable($fail)) {
                return $fail($this);
            }
        }

        return $route;
    }

    public function dispatch(int $status = 200, ?callable $fail = null)
    {
        $route = $this->find();

        if (false !== $route) {
            $this->actual($route);

            if ($route['middleware']) {
                $this->handler->addMiddleware($route['middleware']);
            }

            $target = $route['target'];

            $this->handler->addMiddleware(function () use ($target, $status) {
                if (is_string($target) && fnmatch('*@*', $target)) {
                    $container = Core::app();

                    [$controller, $action] = explode('@', $target, 2);
                    $response = $container->call(
                        [$container->make($controller), $action], $this->params
                    );
                } else {
                    $response = value($target);
                }

                if (is_object($response)) {
                    $classMethods = get_class_methods($response);

                    if (in_array('toArray', $classMethods)) {
                        $response = $response->toArray();
                    } else if (in_array('toJson', $classMethods)) {
                        $response = $response->toJson();
                    } else if (in_array('__toString', $classMethods)) {
                        $response = $response->__toString();
                    } else if (is_callable($response)) {
                        $response = value($response);
                    } else {
                        $response = (array) $response;
                    }
                }

                if (is_array($response)) {
                    $response = json_encode($response, JSON_PRETTY_PRINT);

                    return (new Response($status, [], $response))->withHeader('Content-Type', 'application/json;charset=utf-8');
                }

                return new Response($status, [], $response);
            });

            return $this->handler->handle(ServerRequest::fromGlobals())->withStatus($status);
        }

        $msg404 = 'Error 404';

        if (is_callable($fail)) {
            $msg404 = $fail($this);
        }

        return new Response(404, [], $msg404);
    }

    protected function actual($route)
    {
        Core::set('actual_route', $route);
        Core::set('actual_url', config()->get('url') . $_SERVER['REQUEST_URI']);
    }

    public function isRoute(string $name): bool
    {
        try {
            return Core::get('actual_route')['name'] === $name;
        } catch (Exception $e) {
            return false;
        }
    }

    public function redirect(string $path = '/')
    {
        if (!headers_sent()) {
            http_response_code(302);

            header('Location: ' . $path);
        }

        exit;
    }

    public function action(string $route, array $params = [])
    {
        $this->redirect($this->route($route, $params));
    }

    public function redirectTo(string $name, array $params = [])
    {
        $this->redirect($this->url($name, $params));
    }

    public function home(array $params = [])
    {
        $this->redirect($this->url('home', $params));
    }

    public function back()
    {
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    public function asset(string $file): ?string
    {
        if (Str::startsWith($file, 'http')) {
            return $file;
        }

        $asset = public_path($file);

        if (file_exists($asset)) {
            $age = filemtime($asset);

            return config()->get('asset_url') . '/' . $file . '?' . sha1($age);
        }

        return null;
    }

    protected function compileRoute($route)
    {
        if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route, $matches, PREG_SET_ORDER)) {
            $matchTypes = $this->matchTypes;

            foreach($matches as $match) {
                [$block, $pre, $type, $param, $optional] = $match;

                if (isset($matchTypes[$type])) {
                    $type = $matchTypes[$type];
                }

                if ($pre === '.') {
                    $pre = '\.';
                }

                $optional = $optional !== '' ? '?' : null;

                $pattern = '(?:'
                    . ($pre !== '' ? $pre : null)
                    . '('
                    . ($param !== '' ? "?P<$param>" : null)
                    . $type
                    . ')'
                    . $optional
                    . ')'
                    . $optional;

                $route = str_replace($block, $pattern, $route);
            }
        }

        return "`^$route$`u";
    }

    public function setHandler($handler): self
    {
        $this->handler = $handler;

        return $this;
    }
}

// add homepage
//$router->add( 'GET', '/', function() {
//    require __DIR__ . '/views/home.php';
//});

// dynamic named route
//$router->add('GET|POST', '/users/[i:id]/', function($id) {
//    $user = .....
//  require __DIR__ . '/views/user.php';
//}, 'user');

// echo URL to user-details page for ID 5
//echo $router->url( 'user-details', array( 'id' => 5 ) ); // Output: "/users/5"
