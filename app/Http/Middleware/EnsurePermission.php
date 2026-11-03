<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        // Superadmin always has full access.
        if ($user->hasRole('superadmin')) {
            return $next($request);
        }

        // Convert middleware format (manage-users) to spatie format (manage users).
        // Multiple permissions separated by "|" grant access when the user has any one of them.
        $spatiePermissions = array_map(
            fn (string $permission): string => str_replace('-', ' ', $permission),
            explode('|', $permission),
        );

        try {
            abort_unless(
                collect($spatiePermissions)->contains(
                    fn (string $spatiePermission): bool => $user->hasPermissionTo($spatiePermission),
                ),
                403,
            );
        } catch (PermissionDoesNotExist) {
            abort(403);
        }

        return $next($request);
    }
}
