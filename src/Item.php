<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class Item extends Record
{
    use ManyToManyable, Notifiable, Morphable;

    protected ?Db $db = null;
    protected array $original = [];

    public function __construct(Db $db, array $options = [])
    {
        if ($id = $options['id'] ?? null) {
            unset($options['id']);
            $options = array_merge(['id' => $id], $options);
        }

        parent::__construct($options);

        $this->db = $db;
        $this->original = $options;

        Core::set('item_db', $db);
        Core::set('item_record', $this);
    }

    /**
     * @return Item
     */
    public function original(): self
    {
        return new self($this->db ?? Core::getDb($this), $this->original);
    }

    /**
     * @param Item $item
     * @return bool
     */
    public function is(Item $item): bool
    {
        return $item->exists() && $this->exists() && $item->id === $this->id;
    }

    /**
     * @param Item $item
     * @return bool
     */
    public function isNot(Item $item): bool
    {
        return !$this->is($item);
    }

    /**
     * @return Item
     */
    public function touch()
    {
        $this['updated_at'] = time();

        return $this->save();
    }

    /**
     * @param $attributes
     * @return array
     */
    public function only($attributes)
    {
        $results = [];

        foreach (is_array($attributes) ? $attributes : func_get_args() as $attribute) {
            $results[$attribute] = $this[$attribute];
        }

        return $results;
    }

    public function except($attributes)
    {
        $data = $this->toArray();

        foreach (is_array($attributes) ? $attributes : func_get_args() as $attribute) {
            unset($data[$attribute]);
        }

        return $data;
    }

    /**
     * @return bool
     */
    public function isDirty(): bool
    {
        return $this->original !== $this->toArray();
    }

    /**
     * @return bool
     */
    public function isClean(): bool
    {
        return $this->original === $this->toArray();
    }

    /**
     * @return array
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->original as $key => $value) {
            if ($value !== $this[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * @return array
     */
    public function getClean(): array
    {
        $clean = [];

        foreach ($this->original as $key => $value) {
            if ($value === $this[$key]) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param callable|null $callback
     * @return Item
     */
    public function save(?callable $callback = null)
    {
        if ($this->original === $this->toArray() && $this->exists()) {
            return $this;
        }

        /** @var Db $db */
        $db = $this->db ?? Core::getDb($this);

        if ($db) {
            $methods = get_class_methods($db);

            if (is_array($methods) && in_array('validate', $methods)) {
                $db->validate($this);
            }

            return $db->save($this, $callback);
        }

        return $this;
    }

    /**
     * @param callable|null $callback
     * @return bool
     */
    public function delete(?callable $callback = null)
    {
        /** @var Db $db */
        $db = $this->db ?? Core::getDb($this);

        return $db->delete($this, $callback);
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return isset($this['id']);
    }

    /**
     * @param array $toMerge
     * @return Item
     */
    public function copy(array $toMerge = [])
    {
        $db = $this->db ?? Core::getDb($this);

        unset($this["id"], $this["created_at"], $this["updated_at"], $this["deleted_at"]);

        foreach ($toMerge as $key => $value) {
            $this[$key] = $value;
        }

        $values = $this->toArray();

        return (new self($db, $values))->save();
    }

    /**
     * @return string|null
     */
    public function keyCache(): ?string
    {
        if ($this->exists()) {
            /** @var Db $db */
            $db = $this->db ?? Core::getDb($this);

            return sprintf(
                "%s:%s:%s",
                str_replace('\\', ':', Str::lower(get_class($db))),
                $this['id'],
                $this['updated_at']
            );
        }

        return null;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function fill(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * @param array $data
     * @return Item
     */
    public function fillAndSave(array $data): Item
    {
        return $this->fill($data)->save();
    }

    /**
     * @param array $data
     * @return Item
     */
    public function update(array $data): Item
    {
        return $this->fill($data)->save();
    }

    public function fresh(): self
    {
        return $this->original();
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function set($name, $value)
    {
        $method = Str::camel('set_' . Str::lower($name) . '_attribute');

        if (in_array($method, get_class_methods($modeler = $this->modeler()))) {
            $value = $modeler->{$method}($value, $this);
        }

        parent::set($name, $value);

        return $this;
    }

    public function get($name, $default = null)
    {
        $values = $this->toArray();

        if ('wasRecentlyCreated' === $name) {
            if ($this->exists()) {
                return time() - $values['created_at'] <= 60;
            }

            return false;
        }

        $method = Str::camel('get_' . Str::lower($name) . '_attribute');

        if (in_array($method, get_class_methods($modeler = $this->modeler()))) {
            return $modeler->{$method}($this);
        }

        /** @var Db|null $db */
        $db = $this->db ?? Core::getDb($this) ?? null;

        if (null === $db) {
            return parent::get($name, $default);
        }

        if (in_array($name, get_class_methods($db))) {
            return $db->{$name}($this);
        }

        if (fnmatch('*_at', $name) && isset($values[$name]) && is_numeric($values[$name])) {
            return Carbon::createFromTimestamp((int) $values[$name]);
        }

        if (fnmatch('*s', $name) && !isset($values[$name . '_id'])) {
            $concern    = ucfirst(Str::camel(substr($name, 0, -1)));
            $modelName  = ucfirst(class_basename($db));
            $relation   = str_replace('\\' . $modelName, '\\' . $concern, get_class($db));

            return $db->hasMany($relation, null, $this);
        }

        if (isset($values[$name . '_id']) && is_numeric($values[$name . '_id'])) {
            $modelName  = ucfirst(class_basename($db));
            $relation   = str_replace('\\' . $modelName, '\\' . ucfirst(Str::camel($name)), get_class($db));

            return $db->belongsTo($relation, null, $this);
        }

        return parent::get($name, $default);
    }

    public function modeler(): Modeler
    {
        /** @var Modeler $modeler */
        $modeler = app()->make($this->getDb()->getModeler());

        return $modeler;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (in_array($name, get_class_methods($modeler = $this->modeler()))) {
            $arguments[] = $this;

            return $modeler->{$name}(...$arguments);
        }

        $values = $this->toArray();

        /** @var Db|null $db */
        $db = $this->db ?? Core::getDb($this) ?? null;

        if (is_object($db) && in_array($name, get_class_methods($db))) {
            $arguments[] = $this;

            return $db->{$name}(...$arguments);
        }

        if (!array_key_exists($name, $values)) {
            if (fnmatch('*s', $name) && !isset($values[$name . '_id'])) {
                $concern    = ucfirst(Str::camel(substr($name, 0, -1)));
                $modelName  = ucfirst(class_basename($db));
                $relation   = str_replace('\\' . $modelName, '\\' . $concern, get_class($db));

                return $db->hasMany($relation, null, $this);
            }

            if (isset($this[$name . '_id']) && is_numeric($this[$name . '_id'])) {
                $modelName  = ucfirst(class_basename($db));
                $relation   = str_replace('\\' . $modelName, '\\' . ucfirst(Str::camel($name)), get_class($db));

                return $db->belongsTo($relation, null, $this);
            }
        }

        return parent::__call($name, $arguments);
    }

    /**
     * @param string $column
     * @param mixed|null $default
     * @return mixed
     */
    public function value(string $column, $default = null)
    {
        return $this->__get($column) ?? value($default);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        /** @var Db $db */
        $db = Core::fullObjectToArray($this)['db'] ?? Core::get('item_db');

        $row = parent::toArray();

        $with = $db->getWithQuery();

        if (!empty($with)) {
            foreach ($with as $key => $resolver) {
                $row[$key] = $resolver($row, $db);
            }
        }

        return $row;
    }

    /**
     * @return bool
     */
    public function isAuthenticable(): bool
    {
        return $this->authenticable;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getCreatedAt()
    {
        $value = $this->created_at;

        if ($value) {
            return Carbon::createFromTimestamp((int) $value);
        }

        return null;
    }

    public function getUpdatedAt()
    {
        $value = $this->updated_at;

        if ($value) {
            return Carbon::createFromTimestamp((int) $value);
        }

        return null;
    }

    /**
     * @return Db|null
     */
    public function getDb(): ?Db
    {
        return $this->db;
    }
}
