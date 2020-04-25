<?php
namespace Morbihanet\Modeler;

use Closure;
use Exception;
use Faker\Factory;
use ReflectionClass;
use ReflectionFunction;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;

class Core
{
    protected static $data = [];

    const EMAIL_REGEX_LOCAL = '(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){255,})(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){65,}@)(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22))(?:\\.(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22)))*';
    const EMAIL_REGEX_DOMAIN = '(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\\]))';

    public static function get(string $key, $default = null)
    {
        return value(
            Arr::get(
                static::$data,
                $key,
                Arr::get(
                    static::$data,
                    'instance_' . $key,
                    Arr::get(
                        static::$data,
                        'singleton_' . $key,
                        $default
                    )
                )
            )
        );
    }

    public static function set(string $key, $value): self
    {
        static::$data[$key] = $value;

        return new static;
    }

    public static function has(string $key): bool
    {
        return isset(static::$data[$key]);
    }

    public static function singleton(string $key, $value): self
    {
        static::$data['singleton_' . $key] = value($value);

        return new static;
    }

    public static function instance(string $key, $value): self
    {
        static::$data['instance_' . $key] = $value;

        return new static;
    }

    public static function di(): Di
    {
        return Di::getInstance();
    }

    public static function resolve($class, ...$arguments)
    {
        $class = is_object($class) ? get_class($class) : $class;

        if (!class_exists($class)) {
            return null;
        }

        $container = static::di();
        $dependancyClass = new ReflectionClass($class);
        $instanciable = $dependancyClass->isInstantiable();

        if ($instanciable) {
            $constructorArguments = $dependancyClass->getConstructor()->getParameters();

            if (empty($constructorArguments)) {
                return $dependancyClass->newInstance();
            }

            $dependancyClassName = $dependancyClass->getName();

            if (count($arguments) === count($constructorArguments)) {
                $instance = $dependancyClass->newInstanceArgs($arguments);
                $container->set($dependancyClassName, $instance);

                return $instance;
            }

            if ($container->has($dependancyClassName)) {
                return $container->get($dependancyClassName);
            }

            $params = [];

            foreach ($constructorArguments as $param) {
                if ($param->isDefaultValueAvailable()) {
                    $params[] = $param->getDefaultValue();
                } else {
                    if ($classParam = $param->getClass()) {
                        $paramValue = static::resolve($className = $classParam->getName());

                        if (null === $paramValue && !empty($arguments)) {
                            $argValue = array_shift($arguments);

                            if ($argValue instanceof $className) {
                                $paramValue = $argValue;
                            }
                        }

                        $params[] = $paramValue;
                    }
                }
            }

            try {
                $instance = $dependancyClass->newInstanceArgs($params);
                $container->set($dependancyClassName, $instance);

                return $instance;
            } catch (Exception $e) {
                $interfaces = $dependancyClass->getInterfaces();

                foreach ($interfaces as $interface) {
                    $resolvedService = static::resolve($interface->getName());

                    if (null !== $resolvedService) {
                        $container->set($interface->getName(), $resolvedService);

                        return $resolvedService;
                    }
                }

                if ($parentClass = $dependancyClass->getParentClass()) {
                    return static::resolve($parentClass->getName());
                }
            }
        }

        return null;
    }

    public static function resolveClosure($closure, ...$args)
    {
        if (!$closure instanceof Closure) {
            static::exception('Core', "The first argument must be a closure object.");
        }

        if (!$closure instanceof Closure && is_object($closure)) {
            $closure = static::toClosure($closure);
        }

        $ref        = new ReflectionFunction($closure);
        $params     = $ref->getParameters();

        $isVariadic = false;

        if (count($params) === 1) {
            $firstParam = current($params);

            $isVariadic = $firstParam->isVariadic();
        }

        if ($isVariadic) {
            return $closure(...$args);
        }

        if (empty($args) || count($args) !== count($params)) {
            $instanceParams = [];

            foreach ($params as $param) {
                $p = null;

                if (!empty($args)) {
                    $p = array_shift($args);

                    if (is_null($p)) {
                        try {
                            $p = $param->getDefaultValue();
                        } catch (Exception $e) {
                            $p = null;
                        }
                    }

                    $classParam = $param->getClass();

                    if ($classParam) {
                        $c = $classParam->getName();
                        $made = false;

                        if (!$p instanceof $c) {
                            array_unshift($args, $p);

                            try {
                                $p = static::resolve($c);

                                $made = true;
                            } catch (Exception $e) {
                                static::exception('Core', $e->getMessage());
                            }
                        }

                        if (false === $made) {
                            if ($param->hasType()) {
                                $t = (string) $param->getType()->getName();

                                if (is_object($p) && !$p instanceof $t) {
                                    if (true === $param->isDefaultValueAvailable()) {
                                        $p = $param->getDefaultValue();
                                    }
                                }
                            }
                        } else {
                            if ($param->hasType()) {
                                $t = (string) $param->getType()->getName();

                                if (is_object($p) && get_class($p) !== $t) {
                                    if ($aw = static::resolve($t)) {
                                        $p = $aw;
                                    }
                                }
                            }
                        }
                    } else {
                        if ($param->hasType()) {
                            if ($param->getType()->getName() !== (string) gettype($p)) {
                                if (!empty($args)) {
                                    $found = false;

                                    foreach ($args as $k => $a) {
                                        if (!$found && $param->getType()->getName() === (string) gettype($a)) {
                                            $args[$k] = $p;
                                            $p = $a;
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $classParam = $param->getClass();

                    if ($classParam) {
                        try {
                            $p = static::resolve($classParam->getName());
                        } catch (Exception $e) {
                            static::exception('Core', $e->getMessage());
                        }
                    } else {
                        try {
                            $p = $param->getDefaultValue();
                        } catch (Exception $e) {
                            $attr = request()->get($param->getName());

                            if ($attr) {
                                $p = $attr;
                            } else {
                                static::exception(
                                    'Core',
                                    $param->getName() . " parameter has no default value."
                                );
                            }
                        }
                    }
                }

                $instanceParams[] = $p;
            }

            if (!empty($instanceParams)) {
                return $closure(...$instanceParams);
            } else {
                return value($closure);
            }
        } else {
            return $closure(...$args);
        }
    }

    public static function toClosure($concern)
    {
        return function () use ($concern) {
            return $concern;
        };
    }

    public static function delete(string $key): bool
    {
        $status = static::has($key);

        if ($status) {
            unset(static::$data[$key]);
        }

        return $status;
    }

    public static function now($tz = null)
    {
        return Date::now($tz);
    }

    public static function arrayable($concern): bool
    {
        return is_object($concern) && in_array('toArray', get_class_methods($concern));
    }

    public static function iterator($concern = null)
    {
        $iterator = new Iterator($concern);

        $iterator->macro('list', function ($value, ?string $key = null) use ($iterator) {
            return $iterator->pluck($value, $key);
        });

        $iterator->macro('or', function () use ($iterator) {
            return $iterator->orWhere(...func_get_args());
        });

        static::set('store', $iterator);

        return $iterator;
    }
    public static function store($scope = null): Iterator
    {
        return static::get('store', new Iterator($scope));
    }

    public static function faker(string $lng = 'fr_FR')
    {
        return Factory::create(config('faker.language', $lng));
    }

    /**
     * @param string $namespace
     * @return Cache
     */
    public static function dyndb(string $namespace = 'core')
    {
        static $dbs = [];

        if (!$db = Arr::get($dbs, $namespace)) {
            $db = new Cache($namespace);
            $dbs[$namespace] = $db;
        }

        return $db;
    }

    public static function model(Db $db, array $data = []): Item
    {
        $namespace = config('modeler.item_class', 'DB\\Entities');
        $class = $namespace . '\\' . $cb = class_basename($db);

        if (!class_exists($class)) {
            $code = 'namespace '.$namespace.'; class ' . $cb . ' extends \\Morbihanet\\Modeler\\Item {}';
            eval($code);
        }

        return new $class($db, $data);
    }

    public static function fullObjectToArray($object, bool $recursive = false)
    {
        $original = (array) $object;
        $values = [];

        foreach ($original as $key => $value) {
            $k = str_replace(get_class($object), '', preg_replace('/[\x00*\x00]/', '', $key));

            if (true === $recursive && is_object($value)) {
                $value = static::fullObjectToArray($value, true);
            }

            $values[$k] = $value;
        }

        return $values;
    }

    public static function uncamelize($string, $splitter = "_")
    {
        $string = preg_replace('/(?!^)[[:upper:]][[:lower:]]/', '$0', preg_replace('/(?!^)[[:upper:]]+/', $splitter . '$0', $string));

        return Str::lower($string);

    }

    public static function explodePluckParameters($value, $key)
    {
        $value = is_string($value) ? explode('.', $value) : $value;

        $key = is_null($key) || is_array($key) ? $key : explode('.', $key);

        return [$value, $key];
    }

    public static function compare($actual, $operator = null, $value = null)
    {
        $nargs = func_num_args();

        if ($nargs === 1) {
            return $actual === true;
        }

        if ($nargs === 2) {
            $value = $operator;

            if (is_array($actual) || is_object($actual)) {
                $actual = serialize($actual);
            }

            if (is_array($value) || is_object($value)) {
                $value = serialize($value);
            }

            return sha1($actual) === sha1($value);
        }

        $strings = array_filter([$actual, $value], function ($concern) {
            return is_string($concern) || (is_object($concern) && method_exists($concern, '__toString'));
        });

        if (count($strings) < 2 && count(array_filter([$actual, $value], 'is_object')) === 1) {
            $status = fnmatch('not *', $operator) ? true : false;

            return $status || in_array($operator, ['!=', '<>', '!==']);
        }

        switch ($operator) {
            case '<>':
            case '!=':
                if (is_array($actual) || is_object($actual)) {
                    $actual = serialize($actual);
                }

                if (is_array($value) || is_object($value)) {
                    $value = serialize($value);
                }

                return sha1($actual) != sha1($value);
            case '!==':
                if (is_array($actual) || is_object($actual)) {
                    $actual = serialize($actual);
                }

                if (is_array($value) || is_object($value)) {
                    $value = serialize($value);
                }

                return sha1($actual) !== sha1($value);
            case 'gt':
            case '>':
                return $actual > $value;
            case 'lt':
            case '<':
                return $actual < $value;
            case 'gte':
            case '>=':
                return $actual >= $value;
            case 'lte':
            case '<=':
                return $actual <= $value;
            case 'between':
                $value = !is_array($value)
                    ? explode(',', str_replace([' ,', ', '], ',', $value))
                    : $value
                ;

                return is_array($value) && $actual >= $value[0] && $actual <= $value[1];
            case 'not between':
                $value = !is_array($value)
                    ? explode(',', str_replace([' ,', ', '], ',', $value))
                    : $value
                ;

                return is_array($value) && ($actual < $value[0] || $actual > $value[1]);
            case 'in':
                $value = !is_array($value)
                    ? explode(',', str_replace([' ,', ', '], ',', $value))
                    : $value
                ;

                return in_array($actual, $value);
            case 'not in':
                $value = !is_array($value)
                    ? explode(',', str_replace([' ,', ', '], ',', $value))
                    : $value
                ;

                return !in_array($actual, $value);
            case 'like':
            case 'match':
                $value = str_replace('%', '*', $value);

                return \fnmatch($value, $actual) ? true : false;
            case 'not like':
            case 'not match':
                $value = str_replace('%', '*', $value);

                return \fnmatch($value, $actual) ? false : true;
            case 'instanceof':
                return ($actual instanceof $value);
            case 'not instanceof':
                return (!$actual instanceof $value);
            case 'true':
                return true === $actual;
            case 'false':
                return false === $actual;
            case 'empty':
            case 'null':
            case 'is null':
            case 'is':
                return is_null($actual) || empty($actual);
            case 'not empty':
            case 'not null':
            case 'is not empty':
            case 'is not null':
            case 'is not':
                return !is_null($actual) && !empty($actual);
            case 'regex':
                return preg_match($value, $actual) ? true : false;
            case 'not regex':
                return !preg_match($value, $actual) ? true : false;
            case '=':
            case '==':
                if (is_array($actual) || is_object($actual)) {
                    $actual = serialize($actual);
                }

                if (is_array($value) || is_object($value)) {
                    $value = serialize($value);
                }

                return sha1($actual) == sha1($value);
            case '===':
                if (is_array($actual) || is_object($actual)) {
                    $actual = serialize($actual);
                }

                if (is_array($value) || is_object($value)) {
                    $value = serialize($value);
                }

                return sha1($actual) === sha1($value);
        }

        return false;
    }

    public static function isUtf8(string $string): bool
    {
        if (!is_string($string)) {
            return false;
        }

        return !strlen(
            preg_replace(
                ',[\x09\x0A\x0D\x20-\x7E]'
                . '|[\xC2-\xDF][\x80-\xBF]'
                . '|\xE0[\xA0-\xBF][\x80-\xBF]'
                . '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
                . '|\xED[\x80-\x9F][\x80-\xBF]'
                . '|\xF0[\x90-\xBF][\x80-\xBF]{2}'
                . '|[\xF1-\xF3][\x80-\xBF]{3}'
                . '|\xF4[\x80-\x8F][\x80-\xBF]{2}'
                . ',sS',
                '',
                $string
            )
        );
    }

    public static function unaccent(string $string)
    {
        if (!preg_match('/[\x80-\xff]/', $string)) {
            return $string;
        }

        if (static::isUtf8($string)) {
            $chars = array(
                chr(195).chr(128) => 'A',
                chr(195).chr(129) => 'A',
                chr(195).chr(130) => 'A',
                chr(195).chr(131) => 'A',
                chr(195).chr(132) => 'A',
                chr(195).chr(133) => 'A',
                chr(195).chr(135) => 'C',
                chr(195).chr(136) => 'E',
                chr(195).chr(137) => 'E',
                chr(195).chr(138) => 'E',
                chr(195).chr(139) => 'E',
                chr(195).chr(140) => 'I',
                chr(195).chr(141) => 'I',
                chr(195).chr(142) => 'I',
                chr(195).chr(143) => 'I',
                chr(195).chr(145) => 'N',
                chr(195).chr(146) => 'O',
                chr(195).chr(147) => 'O',
                chr(195).chr(148) => 'O',
                chr(195).chr(149) => 'O',
                chr(195).chr(150) => 'O',
                chr(195).chr(153) => 'U',
                chr(195).chr(154) => 'U',
                chr(195).chr(155) => 'U',
                chr(195).chr(156) => 'U',
                chr(195).chr(157) => 'Y',
                chr(195).chr(159) => 's',
                chr(195).chr(160) => 'a',
                chr(195).chr(161) => 'a',
                chr(195).chr(162) => 'a',
                chr(195).chr(163) => 'a',
                chr(195).chr(164) => 'a',
                chr(195).chr(165) => 'a',
                chr(195).chr(167) => 'c',
                chr(195).chr(168) => 'e',
                chr(195).chr(169) => 'e',
                chr(195).chr(170) => 'e',
                chr(195).chr(171) => 'e',
                chr(195).chr(172) => 'i',
                chr(195).chr(173) => 'i',
                chr(195).chr(174) => 'i',
                chr(195).chr(175) => 'i',
                chr(195).chr(177) => 'n',
                chr(195).chr(178) => 'o',
                chr(195).chr(179) => 'o',
                chr(195).chr(180) => 'o',
                chr(195).chr(181) => 'o',
                chr(195).chr(182) => 'o',
                chr(195).chr(182) => 'o',
                chr(195).chr(185) => 'u',
                chr(195).chr(186) => 'u',
                chr(195).chr(187) => 'u',
                chr(195).chr(188) => 'u',
                chr(195).chr(189) => 'y',
                chr(195).chr(191) => 'y',
                chr(196).chr(128) => 'A',
                chr(196).chr(129) => 'a',
                chr(196).chr(130) => 'A',
                chr(196).chr(131) => 'a',
                chr(196).chr(132) => 'A',
                chr(196).chr(133) => 'a',
                chr(196).chr(134) => 'C',
                chr(196).chr(135) => 'c',
                chr(196).chr(136) => 'C',
                chr(196).chr(137) => 'c',
                chr(196).chr(138) => 'C',
                chr(196).chr(139) => 'c',
                chr(196).chr(140) => 'C',
                chr(196).chr(141) => 'c',
                chr(196).chr(142) => 'D',
                chr(196).chr(143) => 'd',
                chr(196).chr(144) => 'D',
                chr(196).chr(145) => 'd',
                chr(196).chr(146) => 'E',
                chr(196).chr(147) => 'e',
                chr(196).chr(148) => 'E',
                chr(196).chr(149) => 'e',
                chr(196).chr(150) => 'E',
                chr(196).chr(151) => 'e',
                chr(196).chr(152) => 'E',
                chr(196).chr(153) => 'e',
                chr(196).chr(154) => 'E',
                chr(196).chr(155) => 'e',
                chr(196).chr(156) => 'G',
                chr(196).chr(157) => 'g',
                chr(196).chr(158) => 'G',
                chr(196).chr(159) => 'g',
                chr(196).chr(160) => 'G',
                chr(196).chr(161) => 'g',
                chr(196).chr(162) => 'G',
                chr(196).chr(163) => 'g',
                chr(196).chr(164) => 'H',
                chr(196).chr(165) => 'h',
                chr(196).chr(166) => 'H',
                chr(196).chr(167) => 'h',
                chr(196).chr(168) => 'I',
                chr(196).chr(169) => 'i',
                chr(196).chr(170) => 'I',
                chr(196).chr(171) => 'i',
                chr(196).chr(172) => 'I',
                chr(196).chr(173) => 'i',
                chr(196).chr(174) => 'I',
                chr(196).chr(175) => 'i',
                chr(196).chr(176) => 'I',
                chr(196).chr(177) => 'i',
                chr(196).chr(178) => 'IJ',
                chr(196).chr(179) => 'ij',
                chr(196).chr(180) => 'J',
                chr(196).chr(181) => 'j',
                chr(196).chr(182) => 'K',
                chr(196).chr(183) => 'k',
                chr(196).chr(184) => 'k',
                chr(196).chr(185) => 'L',
                chr(196).chr(186) => 'l',
                chr(196).chr(187) => 'L',
                chr(196).chr(188) => 'l',
                chr(196).chr(189) => 'L',
                chr(196).chr(190) => 'l',
                chr(196).chr(191) => 'L',
                chr(197).chr(128) => 'l',
                chr(197).chr(129) => 'L',
                chr(197).chr(130) => 'l',
                chr(197).chr(131) => 'N',
                chr(197).chr(132) => 'n',
                chr(197).chr(133) => 'N',
                chr(197).chr(134) => 'n',
                chr(197).chr(135) => 'N',
                chr(197).chr(136) => 'n',
                chr(197).chr(137) => 'N',
                chr(197).chr(138) => 'n',
                chr(197).chr(139) => 'N',
                chr(197).chr(140) => 'O',
                chr(197).chr(141) => 'o',
                chr(197).chr(142) => 'O',
                chr(197).chr(143) => 'o',
                chr(197).chr(144) => 'O',
                chr(197).chr(145) => 'o',
                chr(197).chr(146) => 'OE',
                chr(197).chr(147) => 'oe',
                chr(197).chr(148) => 'R',
                chr(197).chr(149) => 'r',
                chr(197).chr(150) => 'R',
                chr(197).chr(151) => 'r',
                chr(197).chr(152) => 'R',
                chr(197).chr(153) => 'r',
                chr(197).chr(154) => 'S',
                chr(197).chr(155) => 's',
                chr(197).chr(156) => 'S',
                chr(197).chr(157) => 's',
                chr(197).chr(158) => 'S',
                chr(197).chr(159) => 's',
                chr(197).chr(160) => 'S',
                chr(197).chr(161) => 's',
                chr(197).chr(162) => 'T',
                chr(197).chr(163) => 't',
                chr(197).chr(164) => 'T',
                chr(197).chr(165) => 't',
                chr(197).chr(166) => 'T',
                chr(197).chr(167) => 't',
                chr(197).chr(168) => 'U',
                chr(197).chr(169) => 'u',
                chr(197).chr(170) => 'U',
                chr(197).chr(171) => 'u',
                chr(197).chr(172) => 'U',
                chr(197).chr(173) => 'u',
                chr(197).chr(174) => 'U',
                chr(197).chr(175) => 'u',
                chr(197).chr(176) => 'U',
                chr(197).chr(177) => 'u',
                chr(197).chr(178) => 'U',
                chr(197).chr(179) => 'u',
                chr(197).chr(180) => 'W',
                chr(197).chr(181) => 'w',
                chr(197).chr(182) => 'Y',
                chr(197).chr(183) => 'y',
                chr(197).chr(184) => 'Y',
                chr(197).chr(185) => 'Z',
                chr(197).chr(186) => 'z',
                chr(197).chr(187) => 'Z',
                chr(197).chr(188) => 'z',
                chr(197).chr(189) => 'Z',
                chr(197).chr(190) => 'z',
                chr(197).chr(191) => 's',
                // Euro Sign
                chr(226).chr(130).chr(172) => 'E',
                // GBP (Pound) Sign
                chr(194).chr(163) => '',
                'Ã„' => 'Ae', 'Ã¤' => 'ae', 'Ãœ' => 'Ue', 'Ã¼' => 'ue',
                'Ã–' => 'Oe', 'Ã¶' => 'oe', 'ÃŸ' => 'ss'
            );

            $string = strtr($string, $chars);
        } else {
            $chars['in'] = chr(128) . chr(131) . chr(138) . chr(142) . chr(154) . chr(158)
                . chr(159) . chr(162) . chr(165) . chr(181) . chr(192) . chr(193) . chr(194)
                . chr(195) . chr(196) . chr(197) . chr(199) . chr(200) . chr(201) . chr(202)
                . chr(203) . chr(204) . chr(205) . chr(206) . chr(207) . chr(209) . chr(210)
                . chr(211) . chr(212) . chr(213) . chr(214) . chr(216) . chr(217) . chr(218)
                . chr(219) . chr(220) . chr(221) . chr(224) . chr(225) . chr(226) . chr(227)
                . chr(228) . chr(229) . chr(231) . chr(232) . chr(233) . chr(234) . chr(235)
                . chr(236) . chr(237) . chr(238) . chr(239) . chr(241) . chr(242) . chr(243)
                . chr(244) . chr(245) . chr(246) . chr(248) . chr(249) . chr(250) . chr(251)
                . chr(252) . chr(253) . chr(255);

            $chars['out']       = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";
            $string             = strtr($string, $chars['in'], $chars['out']);

            $doubleChars['in']  = array(
                chr(140),
                chr(156),
                chr(198),
                chr(208),
                chr(222),
                chr(223),
                chr(230),
                chr(240),
                chr(254)
            );

            $doubleChars['out'] = array(
                'OE',
                'oe',
                'AE',
                'DH',
                'TH',
                'ss',
                'ae',
                'dh',
                'th'
            );

            $string = str_replace(
                $doubleChars['in'],
                $doubleChars['out'],
                $string
            );
        }

        return $string;
    }

    public static function notSame($a, $b)
    {
        return $a !== $b;
    }

    public static function pattern($array, $pattern = '*')
    {
        $array = static::arrayable($array) ? $array->toArray() : $array;

        $collection = [];

        if (Arr::isAssoc($array)) {
            foreach ($array as $k => $v) {
                if (fnmatch($pattern, $k)) {
                    $collection[$k] = $v;
                }
            }
        } else {
            foreach ($array as $k) {
                if (fnmatch($pattern, $k)) {
                    $collection[] = $k;
                }
            }
        }

        return $collection;
    }

    public static function session(
        string $namespace = 'web',
        string $userKey = 'user',
        ?string $userModel = null
    ): Session {
        return Session::getInstance($namespace, $userKey, $userModel);
    }

    public static function exception($type, $message, $extends = Exception::class)
    {
        $what   = ucfirst(Str::camel($type . '_exception'));
        $class  = 'Morbihanet\\Modeler\\' . $what;

        if (!class_exists($class)) {
            $code = 'namespace Morbihanet\\Modeler; class ' . $what . ' extends \\' . $extends . ' {}';
            eval($code);
        }

        throw new $class($message);
    }

    /**
     * @param $concern
     * @return Bind
     */
    public static function bind($concern)
    {
        return new Bind($concern);
    }

    /**
     * @param $item
     * @return Store|FileStore|RedisStore|MemoryStore
     */
    public static function getDb($item)
    {
        $name = $item instanceof Item ? Str::lower(class_basename($item)) : Str::lower(static::uncamelize($item));

        return Modeler::factorModel($name);
    }

    public static function resourcesRoutes(
        string $model,
        string $controller,
        $middleware = null,
        ?string $prefix = null
    ) {
        $prefix = $prefix ?? '';
        $plural = Str::plural($model);

        $index = Route::get($prefix . '/' . $plural, $controller . '@index')->name($plural . '_index');
        $create = Route::get($prefix . '/' . $plural . '/create', $controller . '@create')->name($plural . '_create');
        $store = Route::post($prefix . '/' . $plural, $controller . '@store')->name($plural . '_store');
        $show = Route::get($prefix . '/' . $model . '/{id}', $controller . '@show')->name($plural . '_show');
        $edit = Route::get($prefix . '/' . $model . '/{id}/edit', $controller . '@edit')->name($plural . '_edit');
        $update = Route::match(
            ['PUT', 'PATCH'],
            $prefix . '/' . $model . '/{id}',
            $controller . '@update')->name($plural . '_update');
        $destroy = Route::delete($prefix . '/' . $model . '/{id}', $controller . '@destroy')->name($plural . '_destroy');

        if (!is_null($middleware)) {
            if (is_string($middleware) || (is_array($middleware) && !Arr::isAssoc($middleware))) {
                /** @var string|array $middleware */
                $indexMiddleware = $createMiddleware =
                $storeMiddleware = $showMiddleware =
                $editMiddleware = $updateMiddleware =
                $destroyMiddleware = $middleware;
            } else {
                /** @var string|array $indexMiddleware */
                $indexMiddleware = Arr::get($middleware, 'index', '');
                /** @var string|array $createMiddleware */
                $createMiddleware = Arr::get($middleware, 'create', '');
                /** @var string|array $storeMiddleware */
                $storeMiddleware = Arr::get($middleware, 'store', '');
                /** @var string|array $showMiddleware */
                $showMiddleware = Arr::get($middleware, 'show', '');
                /** @var string|array $editMiddleware */
                $editMiddleware = Arr::get($middleware, 'edit', '');
                /** @var string|array $updateMiddleware */
                $updateMiddleware = Arr::get($middleware, 'update', '');
                /** @var string|array $destroyMiddleware */
                $destroyMiddleware = Arr::get($middleware, 'destroy', '');
            }

            $index->middleware($indexMiddleware);
            $create->middleware($createMiddleware);
            $store->middleware($storeMiddleware);
            $show->middleware($showMiddleware);
            $edit->middleware($editMiddleware);
            $update->middleware($updateMiddleware);
            $destroy->middleware($destroyMiddleware);
        }
    }

    public static function bearer(string $name = 'app_bearer')
    {
        if (!$cookie = Arr::get($_COOKIE, $name)) {
            $cookie = sha1(uniqid(sha1(uniqid(null, true)), true));
        }

        setcookie($name, $cookie, strtotime('+1 year'), '/');

        return $cookie;
    }

    public static function getToken()
    {
        /** @var MiddlewareCsrf $csrf */
        $csrf = static::get('csrf');

        return $csrf->generateToken();
    }

    public static function isValidEmail(string $email): bool
    {
        $pattern = sprintf('/^(?<local>%s)@(?<domain>%s)$/iD', static::EMAIL_REGEX_LOCAL, static::EMAIL_REGEX_DOMAIN);

        if (!preg_match($pattern, $email, $matches)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $content
     * @param array|string[] $exceptStart
     * @param string $exploder
     * @return array|false|string[]
     */
    public static function parseLines(string $content, array $exceptStart = ['#'], string $exploder = "\n")
    {
        $lines = explode($exploder, $content);

        $lines = array_map('trim', $lines);
        $lines = array_map('strtolower', $lines);

        $lines = array_filter($lines, function ($line) use ($exceptStart) {
            if (empty($line)) {
                return false;
            }

            if (true === Str::startsWith($line, $exceptStart)) {
                return false;
            }

            return $line;
        });

        return $lines;
    }

    public static function validator(): \Illuminate\Validation\Factory
    {
        /** @var \Illuminate\Validation\Factory $factory */
        $factory = app(\Illuminate\Contracts\Validation\Factory::class);

        $factory->resolver(function (array $data, array $rules, array $messages = [], array $attributes = []) {
            return new Validator($data, $rules, $messages, $attributes);
        });

        return $factory;
    }

    public static function validate(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        return static::validator()->make($data, $rules, $messages, $attributes)->validate();
    }

    public static function validateRequest(array $rules, array $messages = [], array $attributes = []): array
    {
        return static::validate(request()->all(), $rules, $messages, $attributes);
    }

    public static function csrf_field()
    {
        /** @var MiddlewareCsrf $csrf */
        $csrf = static::get('csrf');

        return '<input
type="hidden"
name="'.$csrf->getFormKey().'"
value="'.static::getToken().'"
>';
    }
}
