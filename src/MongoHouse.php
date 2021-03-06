<?php
namespace Morbihanet\Modeler;

use ArrayAccess;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Exception\DumpException;

class MongoHouse extends MongoModel implements ArrayAccess
{
    /** @var array */
    protected $guarded        = [];
    protected $namespace      = 'core';
    protected $collection     = 'kvc';
    protected $table          = 'kv';
    protected $primaryKey     = 'k';
    public $timestamps        = false;
    public $incrementing      = false;
    protected $dates          = ['called_at'];
    protected static $memory  = [];
    protected static $cleaned = [];

    public static function boot()
    {
        parent::boot();
        static::clean('core');
    }

    /**
     * @return array
     */
    public function getMemory(): array
    {
        return static::$memory;
    }

    /**
     * @param string $pattern
     * @return array
     */
    public function keys(string $pattern = '*')
    {
        static::clean($this->namespace);

        $pattern = str_replace('*', '%', $pattern);

        $rows = static::select('k')
            ->where('k', 'like', $this->namespace . '.' . $pattern)
            ->get()
        ;

        $collection = [];

        foreach ($rows as $row) {
            array_push($collection, str_replace($this->namespace . '.', '', $row->getAttribute('k')));
        }

        return $collection;
    }

    /**
     * @param string $pattern
     * @param string $search
     * @return array
     */
    public function search(string $pattern, string $search)
    {
        static::clean($this->namespace);

        $rows = static::select('k', 'v')
            ->where('k', 'like', $this->namespace . '.' . str_replace('*', '%', $pattern))
            ->where('v', 'like', str_replace('*', '%', $search))
            ->get()
        ;

        $collection = [];

        /** @var MongoModel $row */
        foreach ($rows as $row) {
            $collection[str_replace($this->namespace . '.', '', $row->getAttribute('k'))] = unserialize($row->getAttribute('v'));
        }

        return $collection;
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function read(string $key, $default = null)
    {
        $key = $this->makeKey($key);

        if (isset(static::$memory[$key])) {
            return static::$memory[$key];
        }

        $row = $this->firstOrCreate(['k' => $key]);

        $value = $row->getAttribute('v');

        $row->update(['called_at' => Core::now()]);

        if (empty($value)) {
            return $default;
        }

        $value = unserialize($value);

        static::$memory[$key] = $value;

        return $value;
    }

    /**
     * @param $key
     * @param int $expire
     * @param int $by
     * @return int
     */
    public function incr($key, $expire = 0, $by = 1)
    {
        $key = $this->makeKey($key);

        $row = $this->firstOrCreate(['k' => $key]);

        $value = $row->getAttribute('v');

        if (empty($value)) {
            $old = 0;
        } else {
            $old = (int) unserialize($value);
        }

        $new = $old + $by;

        $update = ['v' => serialize($new), 'called_at' => Core::now()];

        if (0 < $expire) {
            $update['e'] = time() + ($expire * 60);
        }

        $row->update($update);

        static::$memory[$key] = $new;

        return $new;
    }

    /**
     * @param $key
     * @param int $expire
     * @param int $by
     * @return int
     */
    public function decr($key, $expire = 0, $by = 1)
    {
        return  $this->incr($key, $expire, $by * -1);
    }

    /**
     * @param string $namespace
     * @return mixed
     */
    public static function clean(string $namespace)
    {
        static::$cleaned[$namespace] = true;

        return static::where('e', '>', 0)->where('e', '<', time())->delete();
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function __isset($offset)
    {
        $key = $this->makeKey($offset);

        if (isset(static::$memory[$key])) {
            return true;
        }

        $row = $this->find($key);

        return $row ? true : false;
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function __get($offset)
    {
        $key = $this->makeKey($offset);

        if (isset(static::$memory[$key])) {
            return static::$memory[$key];
        }

        /** @var MongoModel $row */
        if ($row = $this->find($key)) {
            $row->update(['called_at' => Core::now()]);

            return static::$memory[$key] = unserialize($row->getAttribute('v'));
        }

        return null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    /**
     * @param string $offset
     * @param $value
     * @param int $expire minutes to expire
     * @return $this
     */
    public function expire(string $offset, $value, $expire = 0)
    {
        $e = 0 < $expire ? time() + (60 * $expire) : 0;

        $key = $this->makeKey($offset);

        static::$memory[$key] = $value;

        $row = $this->firstOrCreate(['k' => $key]);
        $row->update(['v' => serialize($value), 'e' => $e]);

        return $this;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function __set($offset, $value)
    {
        $key = $this->makeKey($offset);

        static::$memory[$key] = $value;

        $row = $this->firstOrCreate(['k' => $key]);
        $row->update(['v' => serialize($value)]);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

    /**
     * @param mixed $offset
     */
    public function __unset($offset)
    {
        unset(static::$memory[$key = $this->makeKey($offset)]);

        $this->destroy($key);
    }

    /**
     * @return int
     */
    public function flush(): int
    {
        $i = 0;

        foreach($this->keys() as $key) {
            ++$i;
            unset($this[$key]);
        }

        return $i;
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        $collection = [];

        foreach($this->keys() as $key) {
            $collection[$key] = $this[$key];
        }

        return $collection;
    }

    /**
     * @return Collection
     */
    public function toCollection(): Collection
    {
        return collect($this->getAll());
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->getAll();
    }

    /**
     * @param  int $inline
     * @param  int $indent
     * @return string
     * @throws DumpException
     */
    public function toYml($inline = 3, $indent = 2)
    {
        return Yaml::dump($this->toArray(), $inline, $indent, 0);
    }

    /**
     * @param int $option
     * @return string
     */
    public function toJson($option = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->getAll(), $option);
    }

    /**
     * @param string $key
     * @return string
     */
    private function makeKey(string $key): string
    {
        if (!isset(static::$cleaned[$this->namespace])) {
            static::clean($this->namespace);
        }

        return $this->namespace . '.' . $key;
    }

    /**
     * @param string $namespace
     * @return MongoHouse
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }
}
