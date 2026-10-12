<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MobileAuthApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $data['email'])->first();
        abort_unless($user && $user->status === 'active' && Hash::check($data['password'], $user->password), 422, 'Credenciales inválidas.');
        $token = $user->createToken('expo-mobile', ['mobile'])->plainTextToken;
        $user->update(['last_login_at' => now()]);

        return response()->json(['data' => ['token' => $token, 'user' => $user->only(['id', 'name', 'email'])]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['data' => ['revoked' => true]]);
    }
}
