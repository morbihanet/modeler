<?php
namespace Morbihanet\Modeler;

class Can
{
    protected $target = null;
    protected ?Item $user = null;
    protected array $resolvers = [];

    public function __construct($target)
    {
        $this->setTarget($target)->setUser(Guard::user());
    }

    public function check(string $permission, bool $allTrue = false): bool
    {
        $resolvers = $this->resolvers[$permission] ?? [];

        if (empty($resolvers)) {
            return false;
        }

        $hasFalse = false;
        $hasTrue = false;

        foreach ($resolvers as $resolver) {
            if (is_bool($resolver)) {
                $status =  $resolver;
            } elseif (is_callable($resolver)) {
                $status = $resolver($this->getTarget(), $this->getUser());
            } else {
                if (class_exists($resolver) && in_array('handle', get_class_methods())) {
                    $instance = new $resolver($this);

                    $status = $instance->handle($this->getTarget(), $this->getUser());
                }
            }

            if (!$hasFalse) {
                $hasFalse = !$status;
            }

            if (!$hasTrue) {
                $hasTrue = $status;
            }
        }

        return $allTrue ? $hasTrue && !$hasFalse : $hasTrue;
    }

    public function authorize(string $permission): self
    {
        $this->resolvers[$permission][] = true;

        return $this;
    }

    public function forbid(string $permission): self
    {
        $this->resolvers[$permission][] = false;

        return $this;
    }

    public function define(string $permission, callable $resolver): self
    {
        $this->resolvers[$permission][] = $resolver;

        return $this;
    }

    public function setTarget($target): self
    {
        $this->target = $target;

        return $this;
    }

    public function getTarget()
    {
        return $this->target;
    }

    public function setUser(Item $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Item|null
     */
    public function getUser(): ?Item
    {
        return $this->user;
    }
}
