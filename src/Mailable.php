<?php
namespace Morbihanet\Modeler;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable as Base;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Mailable extends Base implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?string $template = null;
    protected array $data = [];

    public function __construct(string $driver = 'remote')
    {
        app('config')->set('mail.driver', $driver);
    }

    public function setDriver(string $driver): self
    {
        app('config')->set('mail.driver', $driver);

        return $this;
    }

    public function set(string $key, $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function pushToQueue()
    {
        return Mail::queue($this);
    }

    public static function toQueue(callable $factory)
    {
        return tap(new static, $factory)->pushToQueue();
    }

    public function locale($locale): self
    {
        $this->locale = $locale;

        Carbon::setLocale($this->locale);
        App::setLocale($this->locale);
        Date::setLocale($this->locale);

        return $this;
    }

    public function build(array $data = []): self
    {
        $this->data = array_merge($this->data, $data);

        return $this->sendMail();
    }

    protected function sendMail(): self
    {
        return $this->subject($this->subject)
            ->view($this->template)
            ->with($this->data);
    }

    public function setTemplate(?string $template): self
    {
        $this->template = $template;

        return $this->view($this->template);
    }
}
