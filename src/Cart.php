<?php
namespace Morbihanet\Modeler;

use Closure;
use Illuminate\Support\Traits\Macroable;

class Cart
{
    protected ?string $name = null;
    protected ?Session $session = null;
    protected ?Collector $items = null;
    protected static array $instances = [];

    use Macroable {
        Macroable::__call as macroCall;
    }

    public function __construct(string $name, ?Session $session = null)
    {
        $this->name = $name;
        $this->session = $session ?? Core::session(static::class);
        $this->items = $this->session->get($name, new Collector);

        static::$instances[$name] = $this;
    }

    public static function getInstance(string $name, ?Session $session = null): self
    {
        if (!$instance = static::$instances[$name] ?? null) {
            $instance = new static($name, $session);
        }

        return $instance;
    }

    public function addItem($item): self
    {
        $this->session->set($this->name, $this->items->add($item));

        return $this;
    }

    public function removeItem($item)
    {
        $this->session->set($this->name, $this->items->remove($item['id']));

        return $this;
    }

    public function replaceItem($item): self
    {
        return $this->removeItem($item)->addItem($item);
    }

    public function getItem($id)
    {
        return $this->items->find($id);
    }

    public function hasItem($id): bool
    {
        return null !== $this->getItem($id);
    }

    public function updateItem($id, $key, $value = null)
    {
        $item = $this->getItem($id);

        if (is_array($key) && null === $value) {
            foreach ($key as $k => $v) {
                $item[$k] = $v;
            }
        } else {
            $item[$key] = $value;
        }

        return $this->replaceItem($item);
    }

    public function getTotal($callback = null)
    {
        $callback = $callback ?? function($item) {
            return $item['price'] * $item['quantity'];
        };

        return $this->getItems()->sum($callback);
    }

    /**
     * @param null|string|Closure $callback
     * @return mixed
     */
    public function getQuantity($callback = null)
    {
        $callback = $callback ?? 'quantity';

        return $this->getItems()->sum($callback);
    }

    public function getItems(): ?Collector
    {
        return $this->items;
    }

    public function items(): ?Collector
    {
        return $this->getItems();
    }

    public function count(): int
    {
        return $this->getItems()->count();
    }

    public function flash(): bool
    {
        return $this->clear();
    }

    public function clear(): bool
    {
        return $this->session->remove($this->name);
    }

    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        $value = $this->getItems()->{$method}(...$parameters);

        if ($value instanceof Collector) {
            return $this;
        }

        return $value;
    }
}
