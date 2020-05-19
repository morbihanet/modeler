<?php

namespace Morbihanet\Modeler;

use Closure;

class Interceptor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return Event::fire('interceptor.response', $next(Event::fire('interceptor.request', $request)));
    }
}