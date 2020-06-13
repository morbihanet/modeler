<?php
namespace Morbihanet\Modeler;

use Morbihanet\Modeler\Interfaces\Monitor;

class Permitter
{
    /** @var Permitter[]  */
    protected static array $instances = [];

    /** @var Monitor[]  */
    protected array $monitors = [];

    public static function getInstance(?string $name = null): self
    {
        $name = $name ?? Core::getCalledClass();

        if (!isset(static::$instances[$name])) {
            static::$instances[$name] = new static;
        }

        return static::$instances[$name];
    }

    public function check($user, string $permission, $concern = null): bool
    {
        return $this->checkOne($user, $permission, $concern);
    }

    public function checkOne($user, string $permission, $concern = null): bool
    {
        $decisions = $this->decisions($user, $permission, $concern);

        return $decisions['success'] > 0;
    }

    public function checkAll($user, string $permission, $concern = null): bool
    {
        $decisions = $this->decisions($user, $permission, $concern);

        return $decisions['fail']=== 0;
    }

    public function checkUnanimity($user, string $permission, $concern = null): bool
    {
        $decisions = $this->decisions($user, $permission, $concern);

        return $decisions['fail'] < $decisions['success'];
    }

    protected function decisions($user, string $permission, $concern = null): array
    {
        $decisions = ['success' => 0, 'fail' => 0];

        foreach ($this->monitors as $monitor) {
            if ($monitor->able($user, $permission, $concern)) {
                $decision = $monitor->decide($user, $permission, $concern);

                if ($decision === true) {
                    ++$decisions['success'];
                } else {
                    ++$decisions['fail'];
                }
            }
        }

        return $decisions;
    }

    /**
     * @param Monitor[] $monitors
     * @return $this
     */
    public function setMonitors(array $monitors): self
    {
        $this->monitors = $monitors;

        return $this;
    }

    /**
     * @param Monitor|string $monitor
     */
    public function removeMonitor($monitor): self
    {
        $this->monitors = collect($this->monitors)->filter(function ($m) use ($monitor) {
            if (is_string($monitor) && class_exists($monitor)) {
                return get_class($m) !== $monitor;
            } else {
                if ($monitor instanceof Monitor) {
                    return $m !== $monitor;
                }
            }

            return true;
        })->toArray();

        return $this;
    }

    public function addMonitor(Monitor $monitor): self
    {
        $this->monitors[] = $monitor;

        return $this;
    }
}
