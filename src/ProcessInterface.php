<?php
namespace Morbihanet\Modeler;

interface ProcessInterface
{
    public function handle(Request $request, ?ProcessInterface $next = null): Response;
}