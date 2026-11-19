<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VendedorMiddleware
{
    /**
     * Só deixa passar usuários logados do tipo "vendedor".
     * (Use depois do middleware "auth": visitantes vão para o login, não para o 403.)
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::user()?->tipo !== 'vendedor') {
            abort(403);
        }

        return $next($request);
    }
}
