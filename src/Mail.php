<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail as Mailer;

class Mail
{
    public static function helper(string $method, string $content, array $params): void
    {
        Mailer::{$method}($content, function (Message $mail) use ($params) {
            foreach ($params as $key => $value) {
                if (fnmatch('attach*', $key) && strlen($key) > 6) {
                    $key = 'attach';
                }

                if (fnmatch('to*', $key) && strlen($key) > 2) {
                    $key = 'to';
                }

                if (fnmatch('cc*', $key) && strlen($key) > 2) {
                    $key = 'cc';
                }

                if (fnmatch('bcc*', $key) && strlen($key) > 3) {
                    $key = 'bcc';
                }

                $meth = Str::camel($key);

                $mail->{$meth}($value);
            }
        });
    }

    public static function raw($content, array $params): void
    {
        static::helper('raw', $content, $params);
    }

    public static function view(string $view, array $params, array $paramsView = []): void
    {
        $html = view($view, $paramsView)->render();

        foreach ($paramsView as $param => $value) {
            $html = str_replace("##$param##", $value, $html);
        }

        static::helper('html', $html, $params);
    }

    public static function html($html, array $params, array $paramsView = []): string
    {
        foreach ($paramsView as $param => $value) {
            $html = str_replace("##$param##", $value, $html);
        }

        static::helper('html', $html, $params);

        return $html;
    }

    public static function text($content, array $params)
    {
        static::helper('plain', $content, $params);
    }

    public static function __callStatic(string $method, array $arguments)
    {
        return Mailer::{$method}(...$arguments);
    }
}
