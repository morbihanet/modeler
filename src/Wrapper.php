<?php
namespace Morbihanet\Modeler;

use Closure;
use ReflectionFunction;

class Wrapper
{
    protected ?Closure $closure = null;
    protected static array $closures = [];

    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
        static::$closures[$this->id()] = $this;
    }

    public static function getClosure(string $id): ?self
    {
        return static::$closures[$id] ?? null;
    }

    public function infos(): array
    {
        $ref = new ReflectionFunction($this->closure);

        $file = $ref->getFileName();
        $start = $ref->getStartLine();
        $end = $ref->getEndLine();
        $code = Core::getReflectedCode($ref);
        $parameters = $ref->getParameters();

        return compact('file', 'start', 'end', 'code', 'parameters');
    }

    public function id(): string
    {
        return sha1(serialize($this->infos()));
    }

    public function bindTo($newthis, $newscope = 'static'): self
    {
        $this->closure = $this->closure->bindTo($newthis, $newscope);

        return $this;
    }

    public function __invoke(...$args)
    {
        $closure = $this->closure;

        return $closure(...$args);
    }
}
