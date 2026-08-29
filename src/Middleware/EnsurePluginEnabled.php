<?php

namespace Zeno\Plugin\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Zeno\Plugin\Support\PluginState;

final class EnsurePluginEnabled
{
    public function __construct(private PluginState $plugins) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $plugin): Response
    {
        abort_unless($this->plugins->enabled($plugin), 404);

        return $next($request);
    }
}
