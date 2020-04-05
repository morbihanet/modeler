<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;

class FileStore extends Db
{
    /** @var string */
    protected $__prefix;

    public function __construct(array $attributes = [])
    {
        $class = str_replace('\\', '-', Str::lower(get_called_class()));

        $prefix = $this->__prefix = config('modeler.file_dir') . "/{$class}";

        if (!is_dir($prefix)) {
            mkdir($prefix, 0777, true);
        }

        $db = function () {
            $files = $this->glob('*.row');

            foreach ($files as $file) {
                yield $this->unserialize(app('files')->get($file));
            }
        };

        if (!empty($attributes)) {
            $this->__model = $this->model($attributes);
        }

        parent::__construct(Core::iterator($db)->setModel($this));
    }

    /**
     * @param $pattern
     * @return \Generator
     */
    protected function glob($pattern)
    {
        $dir = $this->__prefix;

        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (fnmatch($pattern, $file)) {
                        yield $dir . '/' . $file;
                    }
                }

                closedir($dh);
            }
        }
    }

    /**
     * @param Item $record
     * @return Item
     */
    public function _save(Item $record)
    {
        $prefix = $this->__prefix;

        $file = $prefix . '/' . $record['id'] . '.row';
        file_put_contents($file, $this->serialize($record->toArray()));
        chmod($file, 0777);

        $chmod = !file_exists($prefix . '/lc');

        touch($prefix . '/lc', time());

        if ($chmod) {
            chmod($prefix . '/lc', 0777);
        }

        return $record;
    }

    /**
     * @param Item $record
     * @return bool
     */
    public function _delete(Item $record)
    {
        $prefix = $this->__prefix;

        $file = $prefix . '/' . $record['id'] . '.row';
        $status = unlink($file);
        touch($prefix . '/lc', time());

        return $status;
    }

    /**
     * @return int
     */
    public function makeId(): int
    {
        $file = $this->__prefix . '/ids';
        $id = 1;
        $chmod = false;

        if (file_exists($file)) {
            $old = (int) app('files')->get($file);
            $id = $old + 1;
        } else {
            $chmod = true;
        }

        file_put_contents($file, $id);

        if ($chmod) {
            chmod($file, 0777);
        }

        return (int) $id;
    }

    /**
     * @return int
     */
    public function lastInsertId(): int
    {
        $file = $this->__prefix . '/ids';

        return file_exists($file) ? (int) app('files')->get($file) : 1;
    }

    /**
     * @return int
     */
    public function lastModified(): int
    {
        $file = $this->__prefix . '/lc';

        return file_exists($file) ? filemtime($file) : time();
    }

    /**
     * @param $id
     * @param null $default
     * @return Db|Item|null
     */
    public function find($id, $default = null)
    {
        if (is_array($id)) {
            $db = function () use ($id) {
                foreach ($id as $idRow) {
                    $file = $this->__prefix . '/'.$idRow.'.row';

                    if (file_exists($file)) {
                        yield $this->unserialize(app('files')->get($file));
                    }
                }
            };

            return $this->setEngine(Core::iterator($db)->setModel($this));
        }

        $file = $this->__prefix . '/'.$id.'.row';

        if (file_exists($file)) {
            return $this->model($this->unserialize(app('files')->get($file)));
        }

        return $default;
    }

    /**
     * @param \Closure $callback
     * @return mixed
     * @throws \Throwable
     */
    public function transaction(\Closure $callback)
    {
        if (true === $this->beginTransaction()) {
            try {
                $callbackResult = $callback($this);
            } catch (\Exception $e) {
                $this->rollBack();

                throw $e;
            } catch (\Throwable $e) {
                $this->rollBack();

                throw $e;
            }

            $commit = $this->commit();

            return true === $commit ? $callbackResult : null;
        }

        return null;
    }

    /**
     * @return null|FileStore
     */
    public function beginTransaction()
    {
        $transactionalDir = $this->__prefix . '__trx';
        app('files')->deleteDirectory($transactionalDir);

        if (!is_dir($this->__prefix)) {
            app('files')->makeDirectory($this->__prefix, 0777);
        } else {
            app('files')->deleteDirectory($this->__prefix);
            app('files')->makeDirectory($this->__prefix, 0777);
        }

        $copy = app('files')->copyDirectory($this->__prefix, $transactionalDir);

        if (true === $copy) {
            $this->__prefix = $transactionalDir;

            return $this;
        }

        return null;
    }

    /**
     * @return bool
     */
    public function commit()
    {
        $nativePrefix = str_replace('__trx', '', $this->__prefix);

        $delete = app('files')->deleteDirectory($nativePrefix);
        $copy   = app('files')->copyDirectory($this->__prefix, $nativePrefix);
        $clean  = app('files')->deleteDirectory($this->__prefix);

        $this->__prefix = $nativePrefix;

        return true === $delete && true === $copy && true === $clean;
    }

    /**
     * @return bool
     */
    public function rollback()
    {
        $delete = app('files')->deleteDirectory($this->__prefix);

        $this->__prefix = str_replace('__trx', '', $this->__prefix);

        return $delete;
    }

    public static function empty()
    {
        app('files')->deleteDirectory(config('modeler.file_dir'));
    }

    /**
     * @return bool
     */
    public function flush()
    {
        app('files')->deleteDirectory($this->__prefix);
        $status = !is_dir($this->__prefix);

        mkdir($this->__prefix, true);
        chmod($this->__prefix, 0777);

        return $status;
    }

    /**
     * @return bool
     */
    public function clearCache()
    {
        if (!is_dir($this->__prefix)) {
            return true;
        }

        $min = time() - config('modeler.cache_ttl', 24 * 3600);

        $files = $this->glob('q.*');

        foreach ($files as $file) {
            try {
                if (filemtime($file) <= $min) {
                    unlink($file);
                }
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return int
     */
    public function count()
    {
        $count = 0;

        foreach ($this->glob('*.row') as $row) {
            ++$count;
        }

        return $count;
    }

    /**
     * @param $key
     * @param null $operator
     * @param null $value
     * @return Iterator
     */
    public function where($key, $operator = null, $value = null)
    {
        $this->__where = true;

        $isCallable = 1 === func_num_args() && is_callable($key);

        if (true === $isCallable) {
            $iterator   = $this->getEngine()->where($key, $operator, $value);
            $ids        = [];

            foreach ($iterator as $row) {
                $ids[] = $row['id'];
            }

            unset($iterator);

            return $this->withIds($ids);
        }

        if (false === $this->isCache()) {
            return $this->whereNoCache($key, $operator, $value);
        }

        $file = $this->__prefix . '/q.' . sha1(serialize(func_get_args()) . $this->lastModified());

        if (!file_exists($file)) {
            $ids = $this->whereNoCache($key, $operator, $value, false);

            file_put_contents($file, $this->serialize($ids));
            chmod($file, 0777);
        } else {
            $ids = $this->unserialize(app('files')->get($file));
        }

        return $this->withIds($ids);
    }

    /**
     * @param $key
     * @param null $operator
     * @param null $value
     * @param bool $returnIterator
     * @return Iterator|mixed|null
     */
    public function whereNoCache($key, $operator = null, $value = null)
    {
        $hashkey = sha1(serialize(func_get_args()));

        $ids = static::$__ids[get_called_class()][$hashkey] ?? function () use ($hashkey, $key, $operator, $value) {
            $iterator   = $this->getEngine()->where($key, $operator, $value);
            $ids        = [];

            foreach ($iterator as $row) {
                $ids[] = $row['id'];
            }

            unset($iterator);

            static::$__ids[get_called_class()][$hashkey] = $ids;

            return $ids;
        };

        return $this->withIds(value($ids));
    }
}
