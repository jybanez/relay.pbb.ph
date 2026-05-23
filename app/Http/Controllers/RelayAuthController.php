<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RelayAuthController extends Controller
{
    public function bootstrap(Request $request): JsonResponse
    {
        return response()->json($this->bootstrapPayload($request));
    }

    public function showLogin(): RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/relay');
        }

        return redirect('/?login=1');
    }

    public function login(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $data = $this->attemptSessionLogin($request, $request->boolean('remember'));
        } catch (ValidationException $exception) {
            if ($this->wantsJson($request)) {
                return $this->jsonError(
                    $exception->errors()['email'][0] ?? 'Invalid operator credentials.',
                    $exception->errors(),
                    422,
                );
            }

            return redirect('/?login=1')
                ->withErrors($exception->errors())
                ->withInput($request->only('email'));
        }

        if ($this->wantsJson($request)) {
            return $this->jsonSuccess($data);
        }

        return redirect()->intended('/relay');
    }

    public function apiLogin(Request $request): JsonResponse
    {
        try {
            return $this->jsonSuccess($this->attemptSessionLogin($request, false));
        } catch (ValidationException $exception) {
            return $this->jsonError(
                $exception->errors()['email'][0] ?? 'Invalid operator credentials.',
                $exception->errors(),
                422,
            );
        }
    }

    public function currentUser(Request $request): JsonResponse
    {
        return $this->jsonSuccess([
            'authenticated' => Auth::check(),
            'account' => $this->accountPayload($request->user()),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function csrfToken(Request $request): JsonResponse
    {
        $request->session()->regenerateToken();

        return response()->json([
            'csrfToken' => csrf_token(),
        ]);
    }

    public function sessionPing(Request $request): JsonResponse
    {
        $request->session()->put('relay.session_ping_at', now()->toIso8601String());

        return $this->jsonSuccess([
            'csrf_token' => csrf_token(),
            'touched_at' => now()->toIso8601String(),
            'session_lifetime_minutes' => (int) config('session.lifetime'),
        ]);
    }

    public function relogin(Request $request): JsonResponse
    {
        return $this->apiLogin($request);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function apiLogout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->jsonSuccess([
            'csrf_token' => csrf_token(),
        ]);
    }

    private function finalizeLogin(Request $request): void
    {
        $request->session()->regenerate();

        $user = $request->user();

        if ($user !== null) {
            $user->forceFill([
                'last_login_at' => now(),
            ])->save();
        }
    }

    private function attemptSessionLogin(Request $request, bool $remember): array
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid operator credentials.'],
            ]);
        }

        $user = $request->user();

        if ($user === null || ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => ['Your relay operator account is inactive.'],
            ]);
        }

        $this->finalizeLogin($request);

        return [
            'account' => $this->accountPayload($request->user()),
            'csrf_token' => csrf_token(),
        ];
    }

    private function accountPayload($user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }

    private function bootstrapPayload(Request $request): array
    {
        return [
            'app' => [
                'name' => config('app.name'),
                'page' => $this->resolveBootstrapPage($request),
            ],
            'auth' => [
                'authenticated' => Auth::check(),
                'account' => $this->accountPayload($request->user()),
            ],
            'security' => [
                'csrfToken' => csrf_token(),
                'sessionLifetimeMinutes' => (int) config('session.lifetime'),
                'keepaliveThresholdSeconds' => 120,
            ],
            'settings' => [
                'bootstrapUrl' => '/api/bootstrap',
                'csrfTokenUrl' => '/api/csrf-token',
                'sessionPingUrl' => '/api/session/ping',
            ],
        ];
    }

    private function resolveBootstrapPage(Request $request): string
    {
        $page = trim((string) $request->query('page', ''));

        return $page !== '' ? $page : 'relay';
    }

    private function jsonSuccess(array $data): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $data,
            'meta' => null,
            'error' => null,
        ]);
    }

    private function jsonError(string $message, array $errors = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'status' => false,
            'data' => null,
            'meta' => null,
            'error' => [
                'message' => $message,
                'errors' => $errors,
            ],
        ], $status);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->wantsJson()
            || $request->isXmlHttpRequest();
    }
}
