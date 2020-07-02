<?php
namespace Morbihanet\Modeler;

use Whoops\Handler\Handler;

class Sentry extends Handler
{
    public function handle(?\Exception $e = null)
    {
        $e = $e ?: $this->getException();

        if ($e) {
            if ($sentry = Core::sentry()) {
                $sentry->captureException($e);
            }
        }
    }
}
