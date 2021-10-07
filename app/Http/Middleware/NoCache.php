<?php

namespace App\Http\Middleware;

use Closure;

class NoCache
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
        $response =  $next($request);
        $response->header("Cache-Control", "no-cache, no-store, must-revalidate"); // HTTP 1.1.
        $response->header("Pragma", "no-cache"); // HTTP 1.0.
        $response->header("Expires", "0"); // Proxies.
        return $response;
    }
}
