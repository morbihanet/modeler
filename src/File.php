<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Arr;
use Illuminate\Filesystem\Filesystem;

class File
{
    public static function saveForever(string $key, $value)
    {
        return static::saveFor($key, $value, '10 YEAR');
    }

    public static function saveFor(string $key, $value, string $time = '1 DAY')
    {
        $max    = strtotime('+' . $time);
        $file   = static::makeCache($key, $files  = app('files'));

        $now = time();

        if ($files->isFile($file)) {
            $content = unserialize($files->get($file));

            if (Arr::get($content, 'time', $now - 1) < $now) {
                $files->delete($file);
            } else {
                return Arr::get($content, 'data');
            }
        }

        $content = ['date' => date('d/m/Y H:i:s', $max), 'time' => $max, 'data' => $computed = v($value)];

        $files->put($file, serialize($content));

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
        $files  = app('files');
        $file   = static::makeCache($key, $files);

        if ($files->isFile($file)) {
            $age = filemtime($file);

            if ($age < $timestamp) {
                $files->delete($file);
            } else {
                $content = unserialize($files->get($file));

                return $content;
            }
        }

        $files->put($file, serialize($computed = value($value)));
        touch($file, $timestamp);

        return $computed;
    }

    public static function makeCache(string $key, ?Filesystem $files = null): string
    {
        $dir = storage_path('files');

        $hash = sha1($key);

        for ($i = 0; $i <= 6; $i += 2) {
            $dir .= DIRECTORY_SEPARATOR . substr($hash, $i, 2);
        }

        $file = $dir . DIRECTORY_SEPARATOR . $hash . '.cache';

        if (null !== $files) {
            $parts = explode(DIRECTORY_SEPARATOR, $file);
            array_pop($parts);
            $dir = implode(DIRECTORY_SEPARATOR, $parts);

            if (!$files->isDirectory($dir)) {
                $files->makeDirectory($dir, 0777, true, true);
            }
        }

        return $file;
    }
}