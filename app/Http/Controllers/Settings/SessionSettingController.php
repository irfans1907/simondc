<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SessionSettingController extends Controller
{
    /**
     * Show active sessions.
     */
    public function edit(Request $request): Response
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) {
                $agent = $this->parseUserAgent($session->user_agent);

                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'browser' => $agent['browser'],
                    'os' => $agent['os'],
                    'last_activity' => $session->last_activity,
                    'is_current' => $session->id === request()->getSession()->getId(),
                ];
            });

        return Inertia::render('settings/sessions', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * Revoke a session.
     */
    public function destroy(Request $request, string $sessionId): RedirectResponse
    {
        abort_if($sessionId === $request->getSession()->getId(), 400);

        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session revoked.')]);

        return to_route('session-settings.edit');
    }

    /**
     * Parse user agent string to extract browser and OS.
     *
     * @return array{browser: string, os: string}
     */
    private function parseUserAgent(?string $userAgent): array
    {
        if (! $userAgent) {
            return ['browser' => 'Unknown', 'os' => 'Unknown'];
        }

        $browsers = [
            'Edg' => 'Edge',
            'Chrome' => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari' => 'Safari',
            'Opera' => 'Opera',
            'OPR' => 'Opera',
        ];

        $oses = [
            'Windows' => 'Windows',
            'Mac OS' => 'macOS',
            'Linux' => 'Linux',
            'Android' => 'Android',
            'iPhone' => 'iOS',
            'iPad' => 'iOS',
        ];

        $browser = 'Other';
        foreach ($browsers as $key => $name) {
            if (str_contains($userAgent, $key)) {
                $browser = $name;

                break;
            }
        }

        $os = 'Other';
        foreach ($oses as $key => $name) {
            if (str_contains($userAgent, $key)) {
                $os = $name;

                break;
            }
        }

        return ['browser' => $browser, 'os' => $os];
    }
}
