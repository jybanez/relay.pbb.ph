<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRelayOperatorIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect('/?login=1');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            /** @var RedirectResponse $response */
            $response = redirect('/?login=1');

            return $response->withErrors([
                'email' => 'Your relay operator account is inactive.',
            ]);
        }

        return $next($request);
    }
}
