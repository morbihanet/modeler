<?php
namespace Morbihanet\Modeler;

use Closure;
use ArrayAccess;
use Cron\CronExpression;
use Illuminate\Support\Carbon;

class Scheduler implements ArrayAccess
{
    /** @var string */
    protected $expression = '* * * * *';

    /** @var bool */
    protected bool $everyFifteenSeconds = false;

    /** @var bool */
    protected bool $everyThirtySeconds = false;

    /** @var bool */
    protected bool $everyFortyFiveSeconds = false;

    /** @var \DateTimeZone|string */
    protected $timezone;

    /** @var array */
    protected static array $events = [];

    /** @var callable */
    protected $callback;

    /** @var array */
    protected array $parameters;

    /** @var Item|null */
    protected ?Item $user = null;

    /**
     * @param callable $callback
     * @param array $parameters
     */
    public function __construct(callable $callback, array $parameters = [])
    {
        $this->callback = $callback;
        $this->parameters = $parameters;
    }

    /**
     * @param callable $callback
     * @param array $parameters
     * @return Scheduler
     */
    public static function define(callable $callback, array $parameters = []): Scheduler
    {
        static::$events[] = $event = new static($callback, $parameters);

        return $event;
    }

    /**
     * @return int
     */
    public static function run(): int
    {
        set_time_limit(0);
        $done = 0;

        $success = $fails = 0;

        /** @var Scheduler $event */
        foreach (static::$events as $event) {
            if (static::shouldRun($event)) {
                $callback = $event->getCallback();
                $parameters = $event->getParameters();

                try {
                    $callback(...array_merge([$event], $parameters));
                    ++$success;
                } catch (\Exception $e) {
                    ++$fails;
                }

                ++$done;
            }
        }

        if (0 < $done) {
            $item = Schedule::firstOrCreate(['name' => 'cron']);
            $item->success = $success;
            $item->fails = $fails;
            $item->save();
        }

        return $done;
    }

    public function __get(string $key)
    {
        return Core::get('scheduler_' . $key);
    }

    public function __set(string $key, $value)
    {
        Core::set('scheduler_' . $key, $value);
    }

    public function __isset(string $key)
    {
        return Core::has('scheduler_' . $key);
    }

    public function __unset(string $key)
    {
        return Core::delete('scheduler_' . $key);
    }

    /**
     * @param Scheduler $event
     * @return bool
     */
    public static function shouldRun(Scheduler $event): bool
    {
        $last = Schedule::whereName('cron')->first();

        if (!$last) {
            return true;
        }

        /** @var \Carbon\Carbon $date */
        $date = $last->updated_at;

        $diff = time() - $date->timestamp;

        if ($event->isEveryFifteenSeconds() || $event->isEveryThirtySeconds() || $event->isEveryFortyFiveSeconds()) {
            if ($event->isEveryFifteenSeconds()) {
                return $diff >= 15;
            } else if ($event->isEveryThirtySeconds()) {
                return $diff >= 30;
            } else if ($event->isEveryFortyFiveSeconds()) {
                return $diff >= 45;
            }
        }

        return $event->isDue() && $diff >= 60;
    }

    public function user(Item $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return $this
     */
    public function everyFifteenSeconds(): self
    {
        $this->everyFifteenSeconds = true;
        $this->everyThirtySeconds = false;
        $this->everyFortyFiveSeconds = false;

        $this->expression = '* * * * *';

        return $this;
    }

    /**
     * @return $this
     */
    public function everyThirtySeconds(): self
    {
        $this->everyThirtySeconds = true;
        $this->everyFifteenSeconds = false;
        $this->everyFortyFiveSeconds = false;

        $this->expression = '* * * * *';

        return $this;
    }

    /**
     * @return $this
     */
    public function everyFortyFiveSeconds(): self
    {
        $this->everyFortyFiveSeconds = true;
        $this->everyThirtySeconds = false;
        $this->everyFifteenSeconds = false;

        $this->expression = '* * * * *';

        return $this;
    }

    /**
     * @return $this
     */
    public function everyMinute(): self
    {
        return $this->spliceIntoPosition(1, '*');
    }

    /**
     * @return $this
     */
    public function everyFiveMinutes(): self
    {
        return $this->spliceIntoPosition(1, '*/5');
    }

    /**
     * @return $this
     */
    public function everyTenMinutes(): self
    {
        return $this->spliceIntoPosition(1, '*/10');
    }

    /**
     * @return $this
     */
    public function everyFifteenMinutes(): self
    {
        return $this->spliceIntoPosition(1, '*/15');
    }

    /**
     * @return $this
     */
    public function everyThirtyMinutes(): self
    {
        return $this->spliceIntoPosition(1, '0,30');
    }

    /**
     * @return $this
     */
    public function hourly(): self
    {
        return $this->spliceIntoPosition(1, 0);
    }

    /**
     * @param  array|int  $offset
     * @return $this
     */
    public function hourlyAt($offset): self
    {
        $offset = is_array($offset) ? implode(',', $offset) : $offset;

        return $this->spliceIntoPosition(1, $offset);
    }

    /**
     * @return $this
     */
    public function daily(): self
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0);
    }

    /**
     * @param  string  $time
     * @return $this
     */
    public function at(string $time): self
    {
        return $this->dailyAt($time);
    }

    /**
     * @param  string  $time
     * @return $this
     */
    public function dailyAt(string $time): self
    {
        $segments = explode(':', $time);

        return $this->spliceIntoPosition(2, (int) $segments[0])
            ->spliceIntoPosition(1, count($segments) === 2 ? (int) $segments[1] : '0');
    }

    /**
     * @param  int  $first
     * @param  int  $second
     * @return $this
     */
    public function twiceDaily(int $first = 1, int $second = 13): self
    {
        $hours = $first.','.$second;

        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, $hours);
    }

    /**
     * @return $this
     */
    public function weekdays(): self
    {
        return $this->spliceIntoPosition(5, '1-5');
    }

    /**
     * @return $this
     */
    public function weekends(): self
    {
        return $this->spliceIntoPosition(5, '0,6');
    }

    /**
     * @return $this
     */
    public function mondays(): self
    {
        return $this->days(1);
    }

    /**
     * @return $this
     */
    public function tuesdays(): self
    {
        return $this->days(2);
    }

    /**
     * @return $this
     */
    public function wednesdays(): self
    {
        return $this->days(3);
    }

    /**
     * @return $this
     */
    public function thursdays(): self
    {
        return $this->days(4);
    }

    /**
     * @return $this
     */
    public function fridays(): self
    {
        return $this->days(5);
    }

    /**
     * @return $this
     */
    public function saturdays(): self
    {
        return $this->days(6);
    }

    /**
     * @return $this
     */
    public function sundays(): self
    {
        return $this->days(0);
    }

    /**
     * @return $this
     */
    public function weekly(): self
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(5, 0);
    }

    /**
     * @param  int  $day
     * @param  string  $time
     * @return $this
     */
    public function weeklyOn($day, $time = '0:0'): self
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(5, $day);
    }

    /**
     * @return $this
     */
    public function monthly(): self
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1);
    }

    /**
     * @param  int  $day
     * @param  string  $time
     * @return $this
     */
    public function monthlyOn(int $day = 1, string $time = '0:0'): self
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(3, $day);
    }

    /**
     * @param  int  $first
     * @param  int  $second
     * @return $this
     */
    public function twiceMonthly(int $first = 1, int $second = 16): self
    {
        $days = $first.','.$second;

        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, $days);
    }

    /**
     * @return $this
     */
    public function quarterly(): self
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1)
            ->spliceIntoPosition(4, '1-12/3');
    }

    /**
     * @return $this
     */
    public function yearly(): self
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1)
            ->spliceIntoPosition(4, 1);
    }

    /**
     * @param  array|mixed  $days
     * @return $this
     */
    public function days($days): self
    {
        $days = is_array($days) ? $days : func_get_args();

        return $this->spliceIntoPosition(5, implode(',', $days));
    }

    /**
     * @param  \DateTimeZone|string  $timezone
     * @return $this
     */
    public function timezone($timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * @param  int  $position
     * @param  string  $value
     * @return $this
     */
    protected function spliceIntoPosition(int $position, string $value): self
    {
        $segments = explode(' ', $this->expression);

        $segments[$position - 1] = $value;

        return $this->cron(implode(' ', $segments));
    }

    public function cron($expression): self
    {
        $this->expression = $expression;

        return $this;
    }

    protected function isDue(): bool
    {
        $date = Carbon::now();

        if ($this->timezone) {
            $date->setTimezone($this->timezone);
        }

        return CronExpression::factory($this->expression)->isDue($date->toDateTimeString());
    }

    /**
     * @return callable
     */
    public function getCallback(): callable
    {
        return $this->callback;
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return bool
     */
    public function isEveryFifteenSeconds(): bool
    {
        return $this->everyFifteenSeconds;
    }

    /**
     * @return bool
     */
    public function isEveryThirtySeconds(): bool
    {
        return $this->everyThirtySeconds;
    }

    /**
     * @return bool
     */
    public function isEveryFortyFiveSeconds(): bool
    {
        return $this->everyFortyFiveSeconds;
    }

    /**
     * @return Item|null
     */
    public function getUser(): ?Item
    {
        return $this->user;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }
}
