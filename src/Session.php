<?php
namespace Morbihanet\Modeler;

use Traversable;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Session\Store;

class Session extends Store implements
    \ArrayAccess,
    \Countable,
    \IteratorAggregate {
    /** @var string */
    protected $namespace;

    /** @var string */
    protected $userKey;

    /** @var string */
    protected $userModel;

    /** @var string */
    protected $localeKey = '_locale';

    protected $onces = null;

    protected static array $instances = [];

    /**
     * @param string $namespace
     * @param string $userKey
     * @param string|null $userModel
     */
    public function __construct(
        string $namespace = 'web',
        string $userKey = 'user',
        ?string $userModel = null
    ) {
        if (null === $userModel) {
            $userModel = config('modeler.user_model');
        }

        $this->namespace    = $namespace;
        $this->userKey      = $userKey;
        $this->userModel    = $userModel;
    }

    public static function getInstance(
        string $namespace = 'web',
        string $userKey = 'user',
        ?string $userModel = null
    ): self {
        if (!array_key_exists($namespace, static::$instances)) {
            return static::$instances[$namespace] = new static($namespace, $userKey, $userModel);
        }

        return static::$instances[$namespace];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return Session|mixed
     */
    public function once(string $key, $value = 'nullmambo')
    {
        if (null === $this->onces) {
            $this->onces = $this->get('__onces', []);
            $this->set('__onces', []);
        }

        if ($value === 'nullmambo') {
            return Arr::get($this->onces, $key);
        }

        $onces = $this->get('__onces', []);
        $onces[$key] = $value;
        $this->set('__onces', $onces);

        return $this;
    }

    /**
     * @param string $key
     * @param null $default
     * @return null
     */
    public function get($key, $default = null)
    {
        if ($this->has($key)) {
            $value = $_SESSION[$this->makeKey($key)];

            if (!fnmatch('_flash.*', $key) && in_array($key, $flashes = $this->get('_flash.new', []))) {
                foreach ($flashes as $ind => $flash) {
                    if ($flash === $key) {
                        unset($flashes[$ind]);
                    }
                }

                $this->set('_flash.new', $flashes);
                $this->delete($key);
            }

            return $value;
        }

        return $default;
    }

    /**
     * @param string $key
     * @param $value
     * @return Session
     */
    public function set(string $key, $value): self
    {
        $this->check();
        $_SESSION[$index = $this->makeKey($key)] = $value;
        $_SESSION[$index . '._at'] = time();

        return $this;
    }

    /**
     * @param string $key
     * @return int
     */
    public function age(string $key): int
    {
        if ($this->has($key)) {
            $index = $this->makeKey($key);

            return (int) $_SESSION[$index . '._at'];
        }

        return 0;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key): bool
    {
        $this->check();

        return Core::notSame('octodummy', Arr::get($_SESSION, $this->makeKey($key), 'octodummy'));
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        $status = $this->has($key);
        unset($_SESSION[$this->makeKey($key)]);

        return $status;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function forget($key): bool
    {
        return $this->delete($key);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function remove($key): bool
    {
        return $this->delete($key);
    }

    /**
     * @return string
     */
    public function previousUrl(): string
    {
        return $this->get('_previous.url', '/');
    }

    /**
     * @param string $url
     * @return Session
     */
    public function setPreviousUrl($url): self
    {
        return $this->put('_previous.url', $url);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        $this->check();

        return count(Core::pattern($_SESSION, $this->namespace . '.*'));
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return 0 === $this->count();
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);

        $this->forget($key);

        return $value;
    }

    /**
     * @return bool
     */
    public function drop(): bool
    {
        return $this->erase();
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        return session_id();
    }

    /**
     * @return bool
     */
    public function regenerate($destroy = false)
    {
        if (true === $destroy) {
            $this->destroy();
        }

        return session_regenerate_id();
    }

    /**
     * @return bool
     */
    public function destroy(): bool
    {
        $this->erase();

        return session_destroy();
    }

    /**
     * @param string|null $key
     * @param null $default
     * @return mixed|null
     */
    public function user(?string $key = null, $default = null)
    {
        $user = $this->get($this->userKey);

        if (!empty($user)) {
            return null !== $key ? Arr::get($user, $key, $default) : $this->makeUser($user['id']);
        }

        return $default;
    }

    /**
     * @return bool
     */
    public function guest(): bool
    {
        return null === $this->user();
    }

    /**
     * @return bool
     */
    public function logged(): bool
    {
        return null !== $this->user('id');
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @param string $namespace
     * @return Session
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * @return string
     */
    public function getUserKey(): string
    {
        return $this->userKey;
    }

    /**
     * @param string $userKey
     * @return Session
     */
    public function setUserKey(string $userKey): self
    {
        $this->userKey = $userKey;

        return $this;
    }

    /**
     * @return string
     */
    public function getUserModel(): string
    {
        return $this->userModel;
    }

    /**
     * @param string $userModel
     * @return Session
     */
    public function setUserModel(string $userModel): self
    {
        $this->userModel = $userModel;

        return $this;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function makeUser($id)
    {
        return (new $this->userModel)->findOrFail($id);
    }

    /**
     * @return string
     */
    public function getLocaleKey(): string
    {
        return $this->localeKey;
    }

    /**
     * @param string $localeKey
     */
    public function setLocaleKey(string $localeKey): void
    {
        $this->localeKey = $localeKey;
    }

    /**
     * @param \Illuminate\Http\Request $request
     */
    public function makeOld(\Illuminate\Http\Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $this->set('old_input_' . $key, $value);
        }
    }

    /**
     * @return string
     */
    protected function generateSessionId()
    {
        return sha1(
            uniqid('', true) .
            token() .
            microtime(true)
        );
    }

    /**
     * @param $id
     * @return bool
     */
    public function isValidId($id)
    {
        return is_string($id) && preg_match('/^[a-f0-9]{40}$/', $id);
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function increment($key, $by = 1)
    {
        $this->set($key, $value = $this->get($key, 0) + $by);

        return $value;
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function decrement($key, $by = 1)
    {
        return $this->increment($key, $by * -1);
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function incr(string $key, $by = 1)
    {
        return $this->increment($key, $by);
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function decr(string $key, int $by = 1)
    {
        return $this->increment($key, $by * -1);
    }

    /**
     * @param array|string $key
     * @param null $value
     * @return Session
     */
    public function put($key, $value = null): self
    {
        if (is_string($key)) {
            return $this->set($key, $value);
        }

        if (!is_array($key) && null === $value) {
            $key = [$key => $value];
        }

        foreach ($key as $arrayKey => $arrayValue) {
            $this->set($arrayKey, $arrayValue);
        }

        return $this;
    }

    /**
     * @param $data
     * @return Session
     */
    public function many($data): self
    {
        $data = Core::arrayable($data) ? $data->toArray() : $data;

        return $this->put($data);
    }

    /**
     * @param array $attributes
     * @return Session
     */
    public function replace(array $attributes): self
    {
        $this->erase();

        return $this->put($attributes);
    }

    /**
     * @param array $attributes
     * @return Session
     */
    public function merge(array $attributes): self
    {
        return $this->put($attributes);
    }

    /**
     * @param string $key
     * @param $new
     * @param null $default
     * @return mixed
     */
    public function permute(string $key, $new, $default = null)
    {
        $value = $this->pull($key, $default);

        $this->set($key, $new);

        return $value;
    }

    /**
     * @return array
     */
    public function all(): array
    {
        $this->check();

        $keys = Core::pattern($_SESSION, $this->namespace . '.*');

        $collection = [];

        foreach ($keys as $key => $value) {
            if (!fnmatch('*._at', $key)) {
                $collection[str_replace($this->namespace . '.', '', $key)] = $value;
            }
        }

        return $collection;
    }

    /**
     * @param null|string $row
     * @return bool
     */
    public function erase(?string $row = null): bool
    {
        if (null !== $row) {
            return $this->delete($row);
        }

        $this->check();

        foreach (Core::pattern($_SESSION, $this->namespace . '.*') as $key => $value) {
            unset($_SESSION[$key]);
        }

        return 0 === $this->toCollection()->count();
    }

    public function invalidate()
    {
        $this->flush();

        return $this->migrate(true);
    }

    /**
     * @return bool
     */
    public function flush(): bool
    {
        return $this->erase();
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->all();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function toCollection()
    {
        return collect($this->all());
    }

    /**
     * @param int $option
     * @return string
     */
    public function toJson($option = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->all(), $option);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function __isset(string $key)
    {
        return $this->has($key);
    }

    /**
     * @param string $key
     */
    public function __unset(string $key)
    {
        $this->delete($key);
    }

    /**
     * @param string $key
     * @param $value
     */
    public function __set(string $key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * @param string $key
     * @return null
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * @param array $rows
     * @return Session
     */
    public function fill(array $rows = []): self
    {
        foreach ($rows as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     * @return Session
     */
    public function push($key, $value)
    {
        $array = $this->get($key, []);

        $array[] = $value;

        return $this->set($key, $array);
    }

    /**
     * @param string $key
     * @return mixed
     */
    function pushDown(string $key)
    {
        return $this->pull($key);
    }

    /**
     * @param string $key
     * @param bool $value
     * @return Session
     */
    public function flash(string $key, $value = true)
    {
        return $this->set($key, $value)->push('_flash.new', $key)->removeFromOldFlashData([$key]);
    }

    /**
     * @param $key
     * @param $value
     * @return Session
     */
    public function now($key, $value)
    {
        return $this->set($key, $value)->push('_flash.old', $key);
    }

    /**
     * @return Session
     */
    public function reflash(): self
    {
        return $this->mergeNewFlashes($this->get('_flash.old', []))
            ->set('_flash.old', []);
    }

    /**
     * @param null $keys
     * @return mixed
     */
    public function keep($keys = null)
    {
        return $this->mergeNewFlashes($keys = is_array($keys) ? $keys : func_get_args())
            ->removeFromOldFlashData($keys);
    }

    /**
     * @param array $keys
     * @return Session
     */
    protected function mergeNewFlashes(array $keys)
    {
        $values = array_unique(array_merge($this->get('_flash.new', []), $keys));

        return $this->set('_flash.new', $values);
    }

    /**
     * @param array $keys
     * @return Session
     */
    protected function removeFromOldFlashData(array $keys)
    {
        return $this->set('_flash.old', array_diff($this->get('_flash.old', []), $keys));
    }

    /**
     * @param array $value
     * @return Session
     */
    public function flashInput(array $value)
    {
        return $this->flash('_old_input', $value);
    }

    /**
     * @param $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @param $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param $offset
     * @param $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @param $offset
     */
    public function offsetUnset($offset)
    {
        $this->delete($offset);
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return bool|null|Session
     */
    public function __call(string $method, array $parameters)
    {
        if (fnmatch('get*', $method)) {
            $uncamelizeMethod   = uncamelize(lcfirst(substr($method, 3)));
            $key                = Str::lower($uncamelizeMethod);
            $args               = [$key];

            if (!empty($parameters)) {
                $args[] = current($parameters);
            }

            return $this->get(...$args);
        } elseif (fnmatch('set*', $method)) {
            $uncamelizeMethod   = Core::uncamelize(lcfirst(substr($method, 3)));
            $key                = Str::lower($uncamelizeMethod);

            return $this->set($key, current($parameters));
        } elseif (fnmatch('forget*', $method)) {
            $uncamelizeMethod   = Core::uncamelize(lcfirst(substr($method, 6)));
            $key                = Str::lower($uncamelizeMethod);

            return $this->delete($key);
        } elseif (fnmatch('has*', $method)) {
            $uncamelizeMethod   = Core::uncamelize(lcfirst(substr($method, 3)));
            $key                = Str::lower($uncamelizeMethod);

            return $this->has($key);
        }

        if (!empty($parameters)) {
            return $this->set($method, current($parameters));
        }

        return $this->get($method);
    }

    protected function check(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    /**
     * @return bool
     */
    public function alive()
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * @param string $key
     * @return string
     */
    protected function makeKey(string $key): string
    {
        return $this->namespace . '.' . $key;
    }

    /**
     * @return Iterator|Traversable
     */
    public function getIterator()
    {
        return new Iterator($this->all());
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * @return Session
     */
    public function __clone()
    {
        return (new static($this->namespace . '.clone'))->fill($this->all());
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function token()
    {
        return $this->get('_token');
    }

    /**
     * @param string $key
     * @param callable $c
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function getOr(string $key, callable $c)
    {
        if (!$this->has($key)) {
            $value = callThat($c);

            $this->set($key, $value);

            return $value;
        }

        return $this->get($key);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return config('session.cookie');
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->getSessionId();
    }

    /**
     * @param  string $id
     * @return void
     */
    public function setId($id)
    {
        session_id($id);
    }

    /**
     * @return bool
     */
    public function start()
    {
        $this->check();

        return $this->alive();
    }

    /**
     * @return bool
     */
    public function save()
    {
        return true;
    }

    /**
     * @return string
     */
    public function csrf(): string
    {
        return (new MiddlewareCsrf($this))->generateToken();
    }

    /**
     * @param  string|array $key
     * @return bool
     */
    public function exists($key)
    {
        return $this->has($key);
    }

    /**
     * @param  bool $destroy
     * @return bool
     */
    public function migrate($destroy = false)
    {
        return $this->regenerate();
    }

    /**
     * @return bool
     */
    public function isStarted()
    {
        return true;
    }

    /**
     * @return \SessionHandlerInterface
     */
    public function getHandler()
    {
        return new NullSessionHandler;
    }

    /**
     * @return bool
     */
    public function handlerNeedsRequest()
    {
        return false;
    }

    /**
     * @param \Illuminate\Http\Request $request
     */
    public function setRequestOnHandler($request) {}

    /**
     * @return Session
     */
    public function driver(): self { return $this; }
}
