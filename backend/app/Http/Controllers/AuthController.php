<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function csrf(Request $request): JsonResponse
    {
        return response()->json(['token' => $request->session()->token()])
            ->header('Cache-Control', 'no-store');
    }

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => is_string($request->input('email'))
                ? mb_strtolower(trim($request->input('email')))
                : $request->input('email'),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ()\-]{7,20}$/'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ], [
            'name.required' => 'Ingresa tu nombre completo.',
            'name.string' => 'El nombre no es válido.',
            'name.max' => 'El nombre debe tener como máximo 120 caracteres.',
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.string' => 'El correo no es válido.',
            'email.email' => 'Ingresa un correo válido.',
            'email.max' => 'El correo debe tener como máximo 254 caracteres.',
            'email.unique' => 'Este correo ya está registrado.',
            'phone.required' => 'Ingresa tu número de celular.',
            'phone.string' => 'El celular no es válido.',
            'phone.regex' => 'Ingresa un número de celular válido.',
            'password.required' => 'Ingresa tu contraseña.',
            'password.string' => 'La contraseña no es válida.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña debe tener como máximo 128 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        try {
            $user = User::create($validated);
        } catch (UniqueConstraintViolationException $exception) {
            if (! User::where('email', $validated['email'])->exists()) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'email' => ['Este correo ya está registrado.'],
            ]);
        }
        Auth::login($user);
        $request->session()->regenerate();

        return $this->user($request)->setStatusCode(201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:128'],
        ], [
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.string' => 'El correo no es válido.',
            'email.email' => 'Ingresa un correo válido.',
            'email.max' => 'El correo debe tener como máximo 254 caracteres.',
            'password.required' => 'Ingresa tu contraseña.',
            'password.string' => 'La contraseña no es válida.',
            'password.max' => 'La contraseña debe tener como máximo 128 caracteres.',
        ]);
        $credentials['email'] = mb_strtolower(trim($credentials['email']));

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['El correo o la contraseña son incorrectos.'],
            ]);
        }

        $request->session()->regenerate();

        return $this->user($request);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email', 'phone']),
        ])->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
