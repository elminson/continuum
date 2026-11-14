<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the shared judge account usable by everyone: its sign-in details
 * cannot be changed and the account cannot be deleted.
 */
class ProtectDemoAccount
{
    /** @var list<string> */
    private const array BLOCKED = [
        'user-password.update',
        'password.update',
        'profile.destroy',
        'passkey.store',
        'passkey.destroy',
        'two-factor.enable',
        'two-factor.disable',
        'two-factor.confirm',
        'two-factor.regenerate-recovery-codes',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isDemo() !== true || $request->isMethodSafe()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::BLOCKED, true)) {
            $message = __('The demo account cannot change its sign-in details.');

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $message], 403);
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return back();
        }

        if ($request->route()?->getName() === 'profile.update') {
            $request->merge(['name' => $request->user()->name, 'email' => $request->user()->email]);
        }

        return $next($request);
    }
}
