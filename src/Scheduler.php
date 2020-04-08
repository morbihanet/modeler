<?php
namespace Morbihanet\Modeler;

use Cron\CronExpression;
use Illuminate\Support\Carbon;

class Scheduler
{
    /**
     * @var string
     */
    protected $expression = '* * * * *';

    /**
     * @var \DateTimeZone|string
     */
    protected $timezone;

    /** @var array */
    protected static array $events = [];

    /**
     * @var callable
     */
    protected $callback;

    /**
     * @var array
     */
    protected array $parameters;

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
        $done = 0;

        if (static::shouldRun()) {
            set_time_limit(0);

            $success = $fails = 0;

            /** @var Scheduler $event */
            foreach (static::$events as $event) {
                if ($event->isDue()) {
                    $callback = $event->getCallback();
                    $parameters = $event->getParameters();

                    try {
                        $callback(...$parameters);
                        ++$success;
                    } catch (\Exception $e) {
                        ++$fails;
                    }

                    ++$done;
                }
            }

            Schedule::create(compact('success', 'fails'));
        }

        return $done;
    }

    /**
     * @return bool
     */
    protected static function shouldRun(): bool
    {
        $last = Schedule::latest()->first();
        $notEmpty = !empty(static::$events);

        if (!$last) {
            return $notEmpty;
        }

        /** @var \Carbon\Carbon $date */
        $date = $last->created_at;

        $diff = time() - $date->timestamp;

        return $notEmpty && $diff >= 60;
    }

    public function user($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return $this
     */
    public function everyMinute()
    {
        return $this->spliceIntoPosition(1, '*');
    }

    /**
     * @return $this
     */
    public function everyFiveMinutes()
    {
        return $this->spliceIntoPosition(1, '*/5');
    }

    /**
     * @return $this
     */
    public function everyTenMinutes()
    {
        return $this->spliceIntoPosition(1, '*/10');
    }

    /**
     * @return $this
     */
    public function everyFifteenMinutes()
    {
        return $this->spliceIntoPosition(1, '*/15');
    }

    /**
     * @return $this
     */
    public function everyThirtyMinutes()
    {
        return $this->spliceIntoPosition(1, '0,30');
    }

    /**
     * @return $this
     */
    public function hourly()
    {
        return $this->spliceIntoPosition(1, 0);
    }

    /**
     * @param  array|int  $offset
     * @return $this
     */
    public function hourlyAt($offset)
    {
        $offset = is_array($offset) ? implode(',', $offset) : $offset;

        return $this->spliceIntoPosition(1, $offset);
    }

    /**
     * @return $this
     */
    public function daily()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0);
    }

    /**
     * @param  string  $time
     * @return $this
     */
    public function at($time)
    {
        return $this->dailyAt($time);
    }

    /**
     * @param  string  $time
     * @return $this
     */
    public function dailyAt($time)
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
    public function twiceDaily($first = 1, $second = 13)
    {
        $hours = $first.','.$second;

        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, $hours);
    }

    /**
     * @return $this
     */
    public function weekdays()
    {
        return $this->spliceIntoPosition(5, '1-5');
    }

    /**
     * @return $this
     */
    public function weekends()
    {
        return $this->spliceIntoPosition(5, '0,6');
    }

    /**
     * @return $this
     */
    public function mondays()
    {
        return $this->days(1);
    }

    /**
     * @return $this
     */
    public function tuesdays()
    {
        return $this->days(2);
    }

    /**
     * @return $this
     */
    public function wednesdays()
    {
        return $this->days(3);
    }

    /**
     * @return $this
     */
    public function thursdays()
    {
        return $this->days(4);
    }

    /**
     * @return $this
     */
    public function fridays()
    {
        return $this->days(5);
    }

    /**
     * @return $this
     */
    public function saturdays()
    {
        return $this->days(6);
    }

    /**
     * @return $this
     */
    public function sundays()
    {
        return $this->days(0);
    }

    /**
     * @return $this
     */
    public function weekly()
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
    public function weeklyOn($day, $time = '0:0')
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(5, $day);
    }

    /**
     * @return $this
     */
    public function monthly()
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
    public function monthlyOn($day = 1, $time = '0:0')
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(3, $day);
    }

    /**
     * @param  int  $first
     * @param  int  $second
     * @return $this
     */
    public function twiceMonthly($first = 1, $second = 16)
    {
        $days = $first.','.$second;

        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, $days);
    }

    /**
     * @return $this
     */
    public function quarterly()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1)
            ->spliceIntoPosition(4, '1-12/3');
    }

    /**
     * @return $this
     */
    public function yearly()
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
    public function days($days)
    {
        $days = is_array($days) ? $days : func_get_args();

        return $this->spliceIntoPosition(5, implode(',', $days));
    }

    /**
     * @param  \DateTimeZone|string  $timezone
     * @return $this
     */
    public function timezone($timezone)
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * @param  int  $position
     * @param  string  $value
     * @return $this
     */
    protected function spliceIntoPosition(int $position, string $value)
    {
        $segments = explode(' ', $this->expression);

        $segments[$position - 1] = $value;

        return $this->cron(implode(' ', $segments));
    }

    public function cron($expression)
    {
        $this->expression = $expression;

        return $this;
    }

    protected function isDue()
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
}
