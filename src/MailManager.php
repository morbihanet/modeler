<?php
namespace Morbihanet\Modeler;

use Illuminate\Mail\MailManager as Base;

class MailManager extends Base
{
    protected function createRemoteTransport()
    {
        return new RemoteTransport;
    }

    protected function createQueuerTransport()
    {
        return new RemoteTransport(true);
    }
}