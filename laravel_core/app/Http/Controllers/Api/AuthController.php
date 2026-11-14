<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Administrator;
use App\Models\Receptionist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Authenticate Admin or Receptionist and issue Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
            'role'     => 'nullable|string|in:admin,receptionist',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $username = trim($request->input('username'));
        $password = $request->input('password');
        $role = $request->input('role');

        $user = null;
        $userRole = null;

        // If specific role requested, search that model
        if ($role === 'admin') {
            $user = Administrator::where('username', $username)->orWhere('email', $username)->first();
            $userRole = 'admin';
        } elseif ($role === 'receptionist') {
            $user = Receptionist::where('username', $username)->orWhere('email', $username)->first();
            $userRole = 'receptionist';
        } else {
            // Auto-detect role: check Administrator first, then Receptionist
            $user = Administrator::where('username', $username)->orWhere('email', $username)->first();
            if ($user) {
                $userRole = 'admin';
            } else {
                $user = Receptionist::where('username', $username)->orWhere('email', $username)->first();
                if ($user) {
                    $userRole = 'receptionist';
                }
            }
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // Check if account is locked
        if ($user->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is temporarily locked until ' . $user->locked_until->toDateTimeString() . ' due to failed attempts.',
            ], 403);
        }

        // Verify password
        if (!Hash::check($password, $user->password)) {
            $user->increment('failed_login_count');
            if ($user->failed_login_count >= 5) {
                $user->update([
                    'locked_until' => now()->addMinutes(15),
                ]);
            }
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // Reset failed logins on successful verification
        $user->update([
            'failed_login_count' => 0,
            'locked_until'       => null,
        ]);

        // Issue Sanctum token with ability scoping
        $abilities = $userRole === 'admin'
            ? ['*']
            : ['reservations:view', 'reservations:manage', 'accommodations:view'];

        $token = $user->createToken('SantaFeAPI', $abilities)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Authentication successful.',
            'data'    => [
                'token'     => $token,
                'token_type'=> 'Bearer',
                'user'      => [
                    'id'       => $user->id,
                    'username' => $user->username,
                    'email'    => $user->email,
                    'role'     => $userRole,
                ],
            ],
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'       => $user->id,
                'username' => $user->username,
                'email'    => $user->email,
                'role'     => $user->role,
            ],
        ]);
    }

    /**
     * Revoke tokens (logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && method_exists($user, 'currentAccessToken')) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out.',
        ]);
    }
}
