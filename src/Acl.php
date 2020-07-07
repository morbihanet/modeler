<?php

namespace Morbihanet\Modeler;

use Closure;
use Illuminate\Support\Arr;

class Acl
{
    private static array $instances   = [];
    private static array $rights      = [];
    private static array $aliases     = [];

    private string $ns;
    private array $allright = [];

    /**
     * @param string|array|null $config
     * @param string $ns
     */
    public function __construct($config = null, string $ns = 'core')
    {
        $this->ns = $ns;

        if (is_string($config) && file_exists($config)) {
            $config = include $config;
        }

        if (is_array($config)) {
            $this->setRules($config);
        }
    }

    public function setRules(array $config): self
    {
        $ns = $this->ns;

        self::$rights[$ns]  = [];
        self::$aliases[$ns] = [];

        foreach ($config as $roleName => $role) {
            $this->addRole($roleName, Arr::get($role, 'parent', null));

            $resources = Arr::get($role, 'resources', '*');

            if ('*' === $resources) {
                $this->addResource($roleName, '*', '*');
            } else {
                foreach ($resources as $resource => $actions) {
                    if ($actions === '*' && is_numeric($resource)) {
                        $this->allright[$roleName] = true;
                    } else {
                        $this->addResource($roleName, $resource, $actions);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @param string $ns
     * @param string|array|null $config
     * @return Acl
     */
    public static function getInstance(string $ns = 'core', $config = null): self
    {
        if (is_null($config)) {
            if (isset(static::$instances[$ns])) {
                return static::$instances[$ns];
            }
        }

        if (is_string($config) && file_exists($config)) {
            $config = include($config);
        }

        if (!isset(static::$instances[$ns])) {
            static::$instances[$ns] = new static($config, $ns);
        }

        return static::$instances[$ns];
    }

    public function addRole($role, $parent = null)
    {
        $rights = Arr::get(static::$rights[$this->ns], $role, []);

        if (!is_null($parent)) {
            $rights = Arr::get(static::$rights[$this->ns], $parent, []);

            if (!isset(static::$aliases[$this->ns][$parent])) {
                static::$aliases[$this->ns][$parent] = [];
            }

            if (!in_array($role, static::$aliases[$this->ns][$parent])) {
                static::$aliases[$this->ns][$parent][] = $role;
            }
        }

        static::$rights[$this->ns][$role] = $rights;

        return $this;
    }

    public function addResource($role, $resource, $actions)
    {
        if (!is_array($actions)) {
            $actions = [$actions];
        }

        $roles = Arr::get(static::$aliases[$this->ns], $role, []);

        $roles[] = $role;

        foreach ($roles as $roleResource) {
            if (!isset(static::$rights[$this->ns][$roleResource])) {
                static::$rights[$this->ns][$roleResource] = [];
            }

            foreach ($actions as $key => $action) {
                if (!isset(static::$rights[$this->ns][$roleResource][$resource])) {
                    static::$rights[$this->ns][$roleResource][$resource] = [];
                }

                static::$rights[$this->ns][$roleResource][$resource][$key] = $action;
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getAliases(): array
    {
        return static::$aliases[$this->ns];
    }

    /**
     * @return array
     */
    public function getRights(): array
    {
        return static::$rights[$this->ns];
    }

    public function check($role, $action, $resource = 'all')
    {
        if (isset($this->allright[$role])) {
            return true;
        }

        if (!isset(static::$rights[$this->ns][$role])) {
            return false;
        }

        $allRights = Arr::get(static::$rights[$this->ns][$role], '*', false);

        if ($allRights) {
            return true;
        }

        $rights = Arr::get(static::$rights[$this->ns][$role], $resource, []);

        if (!empty($rights)) {
            foreach ($rights as $key => $right) {
                if (is_numeric($key)) {
                    if ($right === $action || $right === '*') {
                        return true;
                    }
                } else {
                    if ($key === $action) {
                        return $right instanceof Closure ? $right() : $right;
                    }
                }
            }
        }

        return false;
    }
}
