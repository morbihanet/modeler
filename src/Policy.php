<?php

namespace Morbihanet\Modeler;

use Closure;

class Policy
{
    /**
     * @var Guard
     */
    protected $gate;

    public function __construct()
    {
        $this->gate = Core::get('guard');
    }

    public function handle($request, Closure $next, $ability, ?Item $item = null)
    {
        if ($this->gate::authorize($ability, $item)) {
            return $next($request);
        }

        return response('unauthorized', 403);
    }
}
