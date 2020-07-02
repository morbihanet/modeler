<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Arr;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File as Base;

/**
 * @@mixin Filesystem
 */
class File
{
    public static function saveForever(string $key, $value)
    {
        return static::saveFor($key, $value, '10 YEAR');
    }

    public static function saveFor(string $key, $value, string $time = '1 DAY')
    {
        $max    = strtotime('+' . $time);
        $file   = static::makeCache($key, static::getInstance());

        $now = time();

        if (static::isFile($file)) {
            $content = unserialize(static::get($file));

            if (Arr::get($content, 'time', $now - 1) < $now) {
                static::delete($file);
            } else {
                return Arr::get($content, 'data');
            }
        }

        $content = ['date' => date('d/m/Y H:i:s', $max), 'time' => $max, 'data' => $computed = v($value)];

        static::put($file, serialize($content));

        return $computed;
    }

    public static function hasUntil(string $key)
    {
        $file = static::makeCache($key);

        return file_exists($file);
    }

    public static function notExpiredUntil(string $key, int $timestamp)
    {
        $file = static::makeCache($key);

        return file_exists($file) && filemtime($file) < $timestamp;
    }

    public static function saveUntil(string $key, int $timestamp, $value = null)
    {
        $file   = static::makeCache($key, static::getInstance());

        if (static::isFile($file)) {
            $age = filemtime($file);

            if ($age < $timestamp) {
                static::delete($file);
            } else {
                $content = unserialize(static::get($file));

                return $content;
            }
        }

        static::put($file, serialize($computed = value($value)));
        touch($file, $timestamp);

        return $computed;
    }

    public static function makeCache(string $key, ?Filesystem $files = null): string
    {
        $dir = storage_path('files');

        $hash = sha1($key);
        $ds = DIRECTORY_SEPARATOR;

        for ($i = 0; $i <= 6; $i += 2) {
            $dir .= $ds . substr($hash, $i, 2);
        }

        $file = $dir . $ds . $hash . '.cache';

        if (null !== $files) {
            $parts = explode($ds, $file);
            array_pop($parts);
            $dir = implode($ds, $parts);

            if (!$files->isDirectory($dir)) {
                $files->makeDirectory($dir, 0777, true, true);
            }
        }

        return $file;
    }

    public static function putHash(string $dir, string $hash, string $data, string $extension, bool $replace = true)
    {
        $path = $dir;
        $ds = DIRECTORY_SEPARATOR;

        for ($i = 0; $i <= 6; $i += 2) {
            $path .= $ds . substr($hash, $i, 2);
        }

        $file = $path . $ds . $hash . '.' . $extension;

        if (!static::isDirectory($path)) {
            static::makeDirectory($path, 0777, true, true);
        } else {
            if (static::exists($file) && $replace) {
                static::delete($file);
            }
        }

        if (!static::exists($file)) {
            static::put($file, $data);
        }

        return $file;
    }

    public static function info(string $path)
    {
        return new Base($path);
    }

    public static function getInstance(): Filesystem
    {
        return app('files');
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return static::getInstance()->{$name}(...$arguments);
    }
}
