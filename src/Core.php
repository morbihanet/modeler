<?php
namespace Morbihanet\Modeler;

use Error;
use Closure;
use Mockery;
use Exception;
use Swift_Mailer;
use Faker\Factory;
use MongoDB\Client;
use ReflectionClass;
use Faker\Generator;
use ReflectionFunction;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use BadMethodCallException;
use MongoDB\Driver\Manager;
use Illuminate\Mail\Message;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Carbon;
use Faker\Provider\fr_FR\Company;
use Illuminate\Http\JsonResponse;
use Jenssegers\Mongodb\Connection;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Faker\Provider\fr_FR\PhoneNumber;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Mail\Transport\Transport;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\DB as DbMaster;
use Illuminate\Validation\Factory as ValidatorFactory;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class Core
{
    protected static array $data = [];
    protected static ?Generator $faker;
    protected static bool $booted = false;

    /** @var string */
    const EMAIL_REGEX_LOCAL = '(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){255,})(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){65,}@)(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22))(?:\\.(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22)))*';

    /** @var string */
    const EMAIL_REGEX_DOMAIN = '(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\\]))';

    protected static ?Container $app = null;

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
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

    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public static function set(string $key, $value)
    {
        static::$data[$key] = $value;

        return $value;
    }

    /**
     * @param string $key
     * @param $value
     * @return static
     */
    public static function setNx(string $key, $value): self
    {
        if (!static::has($key)) {
            static::$data[$key] = $value;
        }

        return new static;
    }

    public static function faker(string $locale = 'fr_FR'): Generator
    {
        if (static::$faker === null) {
            static::$faker = Factory::create($locale);
            static::$faker->addProvider(new Company(static::$faker));
            static::$faker->addProvider(new PhoneNumber(static::$faker));
        }

        return static::$faker;
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset(static::$data[$key]);
    }

    /**
     * @param string $key
     * @param $value
     * @return static
     */
    public static function singleton(string $key, $value): self
    {
        static::$data['singleton_' . $key] = value($value);

        return new static;
    }

    /**
     * @param string $key
     * @param $value
     * @return static
     */
    public static function instance(string $key, $value): self
    {
        static::$data['instance_' . $key] = $value;

        return new static;
    }

    /**
     * @return Di
     */
    public static function di(): Di
    {
        return Di::getInstance();
    }

    public static function db()
    {
        return static::app()['db'];
    }

    /**
     * @param $class
     * @param mixed ...$arguments
     * @return mixed|object|null
     */
    public static function resolve($class, ...$arguments)
    {
        $class = is_object($class) ? get_class($class) : $class;

        if (!class_exists($class)) {
            return null;
        }

        $container = static::di();
        $dependancyClass = new ReflectionClass($class);
        $dependancyClassName = $dependancyClass->getName();

        if ($container->has($dependancyClassName)) {
            return $container->get($dependancyClassName);
        }

        $instanciable = $dependancyClass->isInstantiable();

        if ($instanciable) {
            if (null === ($constructor = $dependancyClass->getConstructor())) {
                $instance = $dependancyClass->newInstanceWithoutConstructor();
                $container->set($dependancyClassName, $instance);

                return $instance;
            }

            $constructorArguments = $constructor->getParameters();

            if (empty($constructorArguments)) {
                $instance = $dependancyClass->newInstance();
                $container->set($dependancyClassName, $instance);

                return $instance;
            }

            if (count($arguments) === count($constructorArguments)) {
                $instance = $dependancyClass->newInstanceArgs($arguments);
                $container->set($dependancyClassName, $instance);

                return $instance;
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

            $params = array_merge($arguments, $params);

            if (count($constructorArguments) === count($params)) {
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
        }

        return null;
    }

    /**
     * @param mixed $closure
     * @param mixed ...$args
     * @return mixed
     */
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

    /**
     * @param mixed $concern
     * @return Closure
     */
    public static function toClosure($concern): Closure
    {
        return function () use ($concern) {
            return $concern;
        };
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function delete(string $key): bool
    {
        $status = static::has($key);

        if ($status) {
            unset(static::$data[$key]);
        }

        return $status;
    }

    /**
     * @param null $tz
     * @return Carbon
     */
    public static function now($tz = null): Carbon
    {
        return Date::now($tz);
    }

    /**
     * @param object $concern
     * @return bool
     */
    public static function arrayable($concern): bool
    {
        return is_object($concern) && in_array('toArray', get_class_methods($concern));
    }

    /**
     * @param null $concern
     * @return Iterator
     */
    public static function iterator($concern = null): Iterator
    {
        $iterator = new Iterator($concern);

        $iterator->macro('list', function ($value, ?string $key = null) use ($iterator) {
            return $iterator->pluck($value, $key);
        });

        $iterator->macro('or', function () use ($iterator) {
            return $iterator->orWhere(...func_get_args());
        });

        return static::set('store', $iterator);
    }

    public static function boot()
    {
        if (false === static::$booted) {
            static::$booted = true;

            if (!headers_sent()) {
                static::bearer();
            }

            JsonResponse::macro('data', function () {
                return $this->getData(true);
            });

            Event::fire('core.boot');
        }
    }

    public static function app($app = null): ?Container
    {
        if (is_object($app)) {
            static::$app = $app;
        }

        return static::$app;
    }

    /**
     * @param null $scope
     * @return Iterator
     */
    public static function store($scope = null): Iterator
    {
        return static::get('store', new Iterator($scope));
    }

    /**
     * @param string $lng
     * @return Generator
     */
    public static function fakerFactory(string $lng = 'fr_FR'): Generator
    {
        return Factory::create(config('faker.language', $lng));
    }

    public static function getModel(
        string $model,
        ?string $database = null,
        array $attributes = [],
        ?string $namespace = null,
        bool $authenticable = false
    ): Model {
        return datum($model, $database, $attributes, $namespace, $authenticable);
    }

    /**
     * @param Closure $transaction
     * @param Closure $onFail
     * @param Closure $onSuccess
     * @param string|null $uuid
     * @return mixed
     */
    public static function transaction(
        Closure $transaction,
        Closure $onFail,
        Closure $onSuccess,
        ?string $uuid = null) {
        $uuid = $uuid === null ? Str::uuid()->toString() : $uuid;

        DbMaster::beginTransaction();

        try {
            context('transaction')->merge([
                'transaction_uuid' => $uuid,
            ]);

            value($transaction);
        } catch (QueryException $e) {
            context('transaction')->merge([
                'transaction_uuid' => $uuid,
                'exception_message' => $e->getMessage(),
            ]);

            $onFail->call($e, $e);

            DbMaster::rollBack();

            throw $e;
        }

        DbMaster::commit();

        return value($onSuccess);
    }

    /**
     * @param string $namespace
     * @return Cache
     */
    public static function dyndb(string $namespace = 'core'): Cache
    {
        static $dbs = [];

        if (!$db = Arr::get($dbs, $namespace)) {
            $db = new Cache($namespace);
            $dbs[$namespace] = $db;
        }

        return $db;
    }

    /**
     * @return Http
     */
    public static function http(): Http
    {
        return app(Http::class);
    }

    /**
     * @param Db $db
     * @param array $data
     * @return Item
     */
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

    /**
     * @param $object
     * @param bool $recursive
     * @return array
     */
    public static function fullObjectToArray($object, bool $recursive = false): array
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

    /**
     * @param string $string
     * @param string $splitter
     * @return string
     */
    public static function uncamelize(string $string, string $splitter = "_"): string
    {
        return Str::lower(preg_replace(
            '/(?!^)[[:upper:]][[:lower:]]/',
            '$0',
            preg_replace('/(?!^)[[:upper:]]+/', $splitter . '$0', $string)
        ));
    }

    public static function explodePluckParameters($value, $key): array
    {
        $value = is_string($value) ? explode('.', $value) : $value;

        $key = is_null($key) || is_array($key) ? $key : explode('.', $key);

        return [$value, $key];
    }

    /**
     * @param $actual
     * @param null $operator
     * @param null $value
     * @return bool
     */
    public static function compare($actual, $operator = null, $value = null): bool
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

    /**
     * @param string $string
     * @return bool
     */
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

    /**
     * @param string $string
     * @return string|string[]
     */
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

    /**
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    public static function notSame($a, $b): bool
    {
        return $a !== $b;
    }

    /**
     * @param array $array
     * @param string $pattern
     * @return array
     */
    public static function pattern(array $array, string $pattern = '*'): array
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

    /**
     * @param string $namespace
     * @param string $userKey
     * @param string|null $userModel
     * @return Session
     */
    public static function session(
        string $namespace = 'web',
        string $userKey = 'user',
        ?string $userModel = null
    ): Session {
        return Session::getInstance($namespace, $userKey, $userModel);
    }

    public static function files(): ?Filesystem
    {
        return app('files');
    }

    /**
     * @param string $type
     * @param string $message
     * @param string $extends
     */
    public static function exception(string $type, string $message, string $extends = Exception::class)
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
    public static function bind($concern): Bind
    {
        return new Bind($concern);
    }

    /**
     * @param $item
     * @return Store|FileStore|RedisStore|MemoryStore|LiteStore
     */
    public static function getDb($item)
    {
        $name = $item instanceof Item ? Str::lower(class_basename($item)) : Str::lower(static::uncamelize($item));

        return Modeler::factorModel($name);
    }

    public static function str()
    {
        return new Str;
    }

    public static function config()
    {
        return Config::getInstance();
    }

    public static function settings()
    {
        return Setting::getInstance();
    }

    public static function options()
    {
        return Option::getInstance();
    }

    /**
     * @param string $model
     * @param string $controller
     * @param mixed|null $middleware
     * @param string|null $prefix
     */
    public static function resourcesRoutes(
        string $model,
        string $controller = ApiController::class,
        $middleware = null,
        ?string $prefix = null
    ) {
        $prefix = $prefix ?? '';
        $plural = Str::plural($model);

        $index  = Route::get($prefix . '/' . $plural, $controller . '@index')->name($plural . '_index');
        $store  = Route::post($prefix . '/' . $plural, $controller . '@store')->name($plural . '_store');
        $show   = Route::get($prefix . '/' . $plural . '/{id}', $controller . '@show')->name($plural . '_show');
        $update = Route::match(
            ['PUT', 'PATCH'],
            $prefix . '/' . $plural . '/{id}',
            $controller . '@update')->name($plural . '_update');
        $destroy = Route::delete($prefix . '/' . $plural . '/{id}', $controller . '@destroy')->name($plural . '_destroy');

        if (ApiController::class !== $controller) {
            $create = Route::get($prefix . '/' . $plural . '/create', $controller . '@create')->name($plural . '_create');
            $edit   = Route::get($prefix . '/' . $plural . '/{id}/edit', $controller . '@edit')->name($plural . '_edit');
        }

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
            $store->middleware($storeMiddleware);
            $show->middleware($showMiddleware);
            $update->middleware($updateMiddleware);
            $destroy->middleware($destroyMiddleware);

            if (ApiController::class !== $controller) {
                $create->middleware($createMiddleware);
                $edit->middleware($editMiddleware);
            }
        }
    }

    /**
     * @param null|string $name
     * @return string
     */
    public static function bearer(?string $name = null): string
    {
        $name = $name ?? config('modeler.cookie_name', 'app_bearer');

        if (!$cookie = Arr::get($_COOKIE, $name)) {
            $cookie = sha1(uniqid(sha1(uniqid(null, true)), true));
        }

        setcookie($name, $cookie, strtotime('+1 year'), '/');

        return $cookie;
    }

    /**
     * @return string
     */
    public static function getToken(): string
    {
        /** @var MiddlewareCsrf $csrf */
        $csrf = static::get('csrf');

        return $csrf->generateToken();
    }

    /**
     * @param string $email
     * @return bool
     */
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

    /**
     * @return ValidatorFactory
     */
    public static function validator(): ValidatorFactory
    {
        /** @var \Illuminate\Validation\Factory $factory */
        $factory = app(\Illuminate\Contracts\Validation\Factory::class);

        $factory->resolver(function (array $data, array $rules, array $messages = [], array $attributes = []) {
            return new Validator($data, $rules, $messages, $attributes);
        });

        return $factory;
    }

    /**
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $attributes
     * @return array
     */
    public static function validate(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        return static::validator()->make($data, $rules, $messages, $attributes)->validate();
    }

    /**
     * @param array $rules
     * @param array $messages
     * @param array $attributes
     * @return array
     */
    public static function validateRequest(array $rules, array $messages = [], array $attributes = []): array
    {
        return static::validate(request()->all(), $rules, $messages, $attributes);
    }

    /**
     * @return string
     */
    public static function csrf_field(): string
    {
        /** @var MiddlewareCsrf $csrf */
        $csrf = static::get('csrf');

        return '<input
type="hidden"
name="'.$csrf->getFormKey().'"
value="'.static::getToken().'"
>';
    }

    public static function helperMail(string $method, string $content, array $params): void
    {
        Mail::{$method}($content, function (Message $mail) use ($params) {
            foreach ($params as $key => $value) {
                if (fnmatch('attach*', $key) && strlen($key) > 6) {
                    $key = 'attach';
                }

                $meth = Str::camel($key);

                $mail->{$meth}($value);
            }
        });
    }

    public static function rawMail($content, array $params, array $paramsView = []): string
    {
        foreach ($paramsView as $param => $value) {
            $content = str_replace("##$param##", $value, $content);
        }

        static::helperMail('raw', $content, $params);

        return $content;
    }

    public static function viewMail(string $view, array $params, array $paramsView = []): string
    {
        $html = view($view, $paramsView)->render();

        foreach ($paramsView as $param => $value) {
            $html = str_replace("##$param##", $value, $html);
        }

        static::helperMail('html', $html, $params);

        return $html;
    }

    public static function htmlMail(string $html, array $params, array $paramsView = []): string
    {
        foreach ($paramsView as $param => $value) {
            $html = str_replace("##$param##", $value, $html);
        }

        static::helperMail('html', $html, $params);

        return $html;
    }

    public static function remoteHtmlMail(string $html, array $params, array $paramsView = []): string
    {
        return static::switchTransport(new RemoteTransport, function () use ($html, $params, $paramsView) {
            return static::htmlMail($html, $params, $paramsView);
        });
    }

    public static function queueHtmlMail(string $html, array $params, array $paramsView = []): string
    {
        return static::switchTransport(new RemoteTransport(true), function () use ($html, $params, $paramsView) {
            return static::htmlMail($html, $params, $paramsView);
        });
    }

    public static function textMail(string $content, array $params, array $paramsView = []): string
    {
        foreach ($paramsView as $param => $value) {
            $content = str_replace("##$param##", $value, $content);
        }

        static::helperMail('plain', $content, $params);

        return $content;
    }

    public static function ake(array $array, string $key, $default = null)
    {
        return Arr::get($array, $key, $default);
    }

    public static function oke($target, string $key, $default = null)
    {
        return data_get($target, $key, $default);
    }

    public static function fgc(string $url, int $max = 5)
    {
        static $round = 0;

        ++$round;

        try {
            return file_get_contents($url);
        } catch (Exception $e) {
            if ($round <= $max) {
                return static::fgc($url, $max);
            }

            return null;
        }
    }

    public static function findIn(string $start, string $end, string $concern, ?string $default = null): ?string
    {
        if (!empty($concern) &&
            !empty($start) &&
            !empty($end) &&
            strstr($concern, $start) &&
            strstr($concern, $end)
        ) {
            $segment = explode($start, $concern, 2)[1];

            if (!empty($segment) && strstr($segment, $end)) {
                return explode($end, $segment, 2)[0];
            }
        }

        return $default;
    }

    public static function forwardCallTo($from, $target, $method, $parameters)
    {
        try {
            return $target->{$method}(...$parameters);
        } catch (Error | BadMethodCallException $e) {
            $pattern = '~^Call to undefined method (?P<class>[^:]+)::(?P<method>[^\(]+)\(\)$~';

            if (! preg_match($pattern, $e->getMessage(), $matches)) {
                throw $e;
            }

            if ($matches['class'] !== get_class($target) ||
                $matches['method'] !== $method) {
                throw $e;
            }

            throw new BadMethodCallException(sprintf(
                'Call to undefined method %s::%s()', get_class($from), $method
            ));
        }
    }

    public static function markdown(): Markdown
    {
        return Container::getInstance()->make(Markdown::class);
    }

    public static function getMock($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        return Mockery::mock($class);
    }

    public static function switchTransport(Transport $transport, callable $event)
    {
        $actual = Mail::getSwiftMailer();

        Mail::setSwiftMailer(new Swift_Mailer($transport));

        $data = $event();

        Mail::setSwiftMailer($actual);

        return $data;
    }

    public static function switchConnexion(string $connection, callable $event)
    {
        $default = DbMaster::getDefaultConnection();

        DbMaster::setDefaultConnection($connection);

        $data = $event();

        DbMaster::setDefaultConnection($default);

        return $data;
    }

    public static function fetch(string $query, array $bindings = [], bool $useReadPdo = true): Iterator
    {
        return static::iterator(function () use ($query, $bindings, $useReadPdo) {
            foreach (DbMaster::select($query, $bindings, $useReadPdo) as $row) {
                yield Record::make((array) $row);
            }
        });
    }

    public static function fetchOne(string $query, array $bindings = [], bool $useReadPdo = true)
    {
        return static::fetch($query, $bindings, $useReadPdo)->first();
    }

    public static function connection(?string $connection = null)
    {
        return DbMaster::connection($connection);
    }

    public static function table(string $table, string $as = null)
    {
        return DbMaster::table($table, $as);
    }

    public static function statement(string $sql, array $bindings = []): bool
    {
        return DbMaster::statement($sql, $bindings);
    }

    public static function upload(string $field, string $name = 'file', ?string $directory = null): bool
    {
        $request = request();
        $directory = $directory ?? storage_path();

        if ($request->hasFile($field) && $request->file($field)->isValid()) {
            $filename = $name . '.' . $request->file($field)->guessExtension();

            try {
                $request->file($field)->move($directory, $filename);

                return true;
            } catch (FileException $e) {
                return false;
            }
        }

        return false;
    }

    public static function fullArray($concern)
    {
        $concern = static::arrayable($concern) ? $concern->toArray() : $concern;

        foreach ((array) $concern as $key => $value) {
            if (static::arrayable($value)) {
                $concern[$key] = static::fullArray($value);
            }
        }

        return $concern;
    }

    public static function mongo()
    {
        if (!$mongo = static::get('core_mongo')) {
            $host = env('MONGO_HOST', 'localhost');
            $port = env('MONGO_PORT', 27017);
            $username = env('MONGO_USER', 'root');
            $password = env('MONGO_PASSWORD', '');
            $connect = true;

            $mongo = new Manager("mongodb://$host:$port", compact('username', 'password', 'connect'));

            static::set('core_mongo', $mongo);
        }

        return $mongo;
    }

    public static function getMongoClient(array $options = [])
    {
        $config = config('database.connections.mongodb', []);
        $options = array_merge(Arr::get($config, 'options', []), $options);

        $hosts = is_array($config['host']) ? $config['host'] : [$config['host']];

        foreach ($hosts as &$host) {
            if (strpos($host, ':') === false && !empty($config['port'])) {
                $host = $host . ':' . $config['port'];
            }
        }

        $auth_database = isset($config['options']) && !empty($config['options']['database'])
            ? $config['options']['database']
            : null;

        $dsn = 'mongodb://' . implode(',', $hosts) . ($auth_database ? '/' . $auth_database : '');

        $driverOptions = [];

        if (isset($config['driver_options']) && is_array($config['driver_options'])) {
            $driverOptions = $config['driver_options'];
        }

        if (!isset($options['username']) && !empty($config['username'])) {
            $options['username'] = $config['username'];
        }

        if (!isset($options['password']) && !empty($config['password'])) {
            $options['password'] = $config['password'];
        }

        return new Client($dsn, $options, $driverOptions);
    }

    public static function getMongoSession()
    {
        return static::mongo()->startSession();
    }
}
