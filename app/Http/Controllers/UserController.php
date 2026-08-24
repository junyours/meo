<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'role' => ['required', 'string', Rule::in(['superadmin', 'admin', 'staff'])],
            'password' => ['nullable', 'string', 'min:8'],
            'email_verified' => ['nullable', 'boolean'],
        ]);

        $plainPassword = $validated['password'] ?? Str::password(12, true, true, false);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($plainPassword),
        ]);

        if (!empty($validated['email_verified'])) {
            $user->email_verified_at = now();
            $user->save();
        }

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user->fresh(),
            'temporary_password' => empty($validated['password']) ? $plainPassword : null,
        ], 201);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'role' => ['required', 'string', Rule::in(['superadmin', 'admin', 'staff'])],
            'email_verified' => ['nullable', 'boolean'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        if (isset($validated['email_verified'])) {
            $user->email_verified_at = $validated['email_verified'] ? ($user->email_verified_at ?? now()) : null;
        }

        $user->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Directly reset/set password for a user.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:8'],
            'auto_generate' => ['nullable', 'boolean'],
        ]);

        $newPassword = !empty($validated['auto_generate']) || empty($validated['password'])
            ? Str::password(12, true, true, false)
            : $validated['password'];

        $user->forceFill([
            'password' => Hash::make($newPassword),
            'remember_token' => Str::random(60),
        ])->save();

        return response()->json([
            'message' => "Password reset successfully for {$user->name}.",
            'user_id' => $user->id,
            'temporary_password' => $newPassword,
        ]);
    }

    /**
     * Send password reset link to user's email.
     */
    public function sendResetLink(User $user): JsonResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => "Password reset link sent to {$user->email}.",
            ]);
        }

        return response()->json([
            'message' => trans($status),
        ], 400);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        if (auth()->id() === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account while logged in.',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User account deleted successfully.',
        ]);
    }
}
