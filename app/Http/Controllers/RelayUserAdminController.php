<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RelayUserAdminController extends Controller
{
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->abortUnlessAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_OPERATOR])],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
        ]);

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'status_message' => 'Relay user created.',
                'redirect_url' => '/relay/user/'.$user->id,
            ]);
        }

        return redirect('/relay/user/'.$user->id)
            ->with('status', 'Relay user created.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $this->abortUnlessAdmin($request);

        if ((int) $request->user()->id === (int) $user->id && $user->is_active) {
            abort(422, 'You cannot deactivate your own account.');
        }

        $user->forceFill([
            'is_active' => ! $user->is_active,
        ])->save();

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'status_message' => $user->is_active ? 'Relay user reactivated.' : 'Relay user deactivated.',
            ]);
        }

        return redirect('/relay/user/'.$user->id)
            ->with('status', $user->is_active ? 'Relay user reactivated.' : 'Relay user deactivated.');
    }

    public function setRole(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $this->abortUnlessAdmin($request);

        $validated = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_OPERATOR])],
        ]);

        if ((int) $request->user()->id === (int) $user->id && $validated['role'] !== User::ROLE_ADMIN) {
            abort(422, 'You cannot remove your own admin role.');
        }

        $user->forceFill([
            'role' => $validated['role'],
        ])->save();

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'status_message' => 'Relay user role updated.',
            ]);
        }

        return redirect('/relay/user/'.$user->id)
            ->with('status', 'Relay user role updated.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $this->abortUnlessAdmin($request);

        $password = Str::password(16);

        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'status_message' => 'Relay user password reset.',
                'generated_password' => $password,
            ]);
        }

        return redirect('/relay/user/'.$user->id)
            ->with('status', 'Relay user password reset.')
            ->with('generated_password', $password);
    }

    private function abortUnlessAdmin(Request $request): void
    {
        abort_unless($request->user()?->isRelayAdmin(), 403);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->wantsJson()
            || $request->isXmlHttpRequest();
    }
}
