<?php
namespace Morbihanet\Modeler;

class Process implements ProcessInterface
{
    public function handle(Request $request, ?ProcessInterface $next = null): Response
    {
        return $next->handle($request);
    }
}