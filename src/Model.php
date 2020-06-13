<?php
namespace Morbihanet\Modeler;

use ArrayAccess;
use Illuminate\Support\Str;
use Morbihanet\Modeler\Traits\Permittable;

class Model extends Modeler implements ArrayAccess
{
    use Permittable;

    protected ?Item $item = null;
    protected array $hidden = [];
    protected array $guarded = [];
    protected array $fillable = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct();

        $this->item = Core::model(static::getDb(), $attributes);
    }

    public static function new(
        string $model,
        ?string $database = null,
        array $attributes = [],
        ?string $namespace = null,
        bool $authenticable = false
    ): self {
        return datum($model, $database, $attributes, $namespace, $authenticable);
    }

    public function offsetExists($offset)
    {
        return isset($this->item[$offset]);
    }

    public function __isset($offset)
    {
        return isset($this->item[$offset]);
    }

    public function offsetGet($offset)
    {
        $value = $this->item[$offset] ?? null;

        return value($value);
    }

    public function __get($offset)
    {
        $value = $this->item[$offset] ?? null;

        return value($value);
    }

    public function offsetSet($offset, $value)
    {
        $this->item[$offset] = $value;
    }

    public function __set($offset, $value)
    {
        $this->item[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->item[$offset]);
    }

    public function __unset($offset)
    {
        unset($this->item[$offset]);
    }

    public function itemClass(): string
    {
        return $this->item ? get_class($this->item) : Item::class;
    }

    public function dbClass(): string
    {
        return get_class(static::getDb());
    }

    public function newModel(array $attributes = []): Item
    {
        $class = $this->itemClass();

        return Event::fire('model:' . get_called_class() . ':newModel', new $class(static::getDb(), $attributes));
    }

    public function guarded(array $guarded): Model
    {
        $this->guarded = $guarded;

        return $this;
    }

    public function fillable(array $fillable): Model
    {
        $this->fillable = $fillable;

        return $this;
    }

    public function toArray(): array
    {
        $data = $this->item->toArray();

        foreach ($data as $key => $value) {
            if (in_array($key, $this->hidden)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    public function save(?callable $callback = null)
    {
        Event::fire('model:' . get_called_class() . ':save', $this);

        $data = $this->toArray();

        if ($this->item->exists()) {
            unset($data['id']);
            unset($data['created_at']);
            unset($data['updated_at']);
        }

        $keys = array_keys($data);

        if (empty($this->guarded)) {
            if (!empty($this->fillable) && $this->fillable !== $keys) {
                foreach ($keys as $key) {
                    if (!in_array($key, $this->fillable)) {
                        Core::exception(
                            'model',
                            "Field $key is not fillable in entity " . get_called_class() . "."
                        );
                    }
                }
            }
        } else {
            foreach ($this->guarded as $key) {
                if (in_array($key, $keys)) {
                    Core::exception(
                        'model',
                        "Field $key is guarded in entity " . get_called_class() . "."
                    );
                }
            }
        }

        return new static($this->item->save($callback)->toArray());
    }

    public function __call($name, $arguments)
    {
        Event::fire('model:' . get_called_class() . ':' . $name, $this);

        if (static::hasMacro($name)) {
            $arguments = array_merge([$this->item], $arguments);

            return $this->macroCall($name, $arguments);
        }

        $uncamelized = Core::uncamelize($name);

        $methodModeler = Str::camel('scope_' . $uncamelized);

        if (in_array($methodModeler, get_class_methods($this))) {
            $args = array_merge([static::getDb()], $arguments);

            return (new static)->{$methodModeler}(...$args);
        }

        if (
            $this->item instanceof Item && (
                in_array($name, get_class_methods($this->item)) ||
                fnmatch('set_*', $uncamelized) ||
                fnmatch('get_*', $uncamelized)
            )) {
            return $this->item->{$name}(...$arguments);
        }

        return parent::__call($name, $arguments);
    }
}
