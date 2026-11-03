<?php

/**
 * Patch Fortify to allow email verification without login.
 *
 * Removes `auth:web` middleware from the email verification route
 * and updates VerifyEmailController to find user by route params
 * instead of requiring authentication. Also redirects to custom
 * verification.verified page after successful verification.
 *
 * Run via: post-update-cmd in composer.json
 */
$root = dirname(__DIR__);
$fortifyRoutes = $root.'/vendor/laravel/fortify/routes/routes.php';
$fortifyController = $root.'/vendor/laravel/fortify/src/Http/Controllers/VerifyEmailController.php';

// Patch 1: Remove auth middleware from verification route
if (file_exists($fortifyRoutes)) {
    $content = file_get_contents($fortifyRoutes);

    $old = "config('fortify.auth_middleware', 'auth').':'.config('fortify.guard'), 'signed', 'throttle:'.\$verificationLimiter";
    $new = "'signed', 'throttle:'.\$verificationLimiter";

    if (str_contains($content, $old)) {
        $content = str_replace($old, $new, $content);
        file_put_contents($fortifyRoutes, $content);
        echo "✓ Patched Fortify routes: removed auth middleware from verification route\n";
    } else {
        echo "ℹ Fortify routes already patched or format changed\n";
    }
}

// Patch 2: Update VerifyEmailController to work without auth + redirect to verification.verified
if (file_exists($fortifyController)) {
    $controllerCode = <<<'PHP'
<?php

namespace Laravel\Fortify\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    /**
     * Mark the user's email address as verified.
     */
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('verification.verified');
        }

        if (! hash_equals(sha1($user->email), $hash)) {
            abort(403, 'Invalid verification link.');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        Auth::loginUsingId($user->id);

        return redirect()->route('verification.verified');
    }
}
PHP;

    file_put_contents($fortifyController, $controllerCode);
    echo "✓ Patched Fortify VerifyEmailController: works without auth, redirects to verification.verified\n";
}
