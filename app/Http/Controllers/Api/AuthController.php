<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SocialLoginCodes;
use App\Services\SubscriptionService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /**
     * No confirma si el correo existe o no (eso permitiría enumerar cuentas
     * registradas): solo valida el formato y responde siempre igual.
     */
    public function checkIdentifier(Request $request)
    {
        $request->validate([
            'identifier' => ['required', 'string', 'email'],
        ]);

        return response()->json([
            'message' => 'Si el correo es válido, podrás continuar con el inicio de sesión o el registro.',
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', 'string', 'in:customer,seller'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'customer',
            'phone' => $validated['phone'] ?? null,
        ]);

        $user->syncRoleFromAdminEmailList();
        // Todo usuario nuevo arranca en el plan gratuito; mejora después
        // desde PUT /api/me/subscription.
        $this->subscriptions->subscribeToDefaultPlan($user);
        $user->sendWelcomeEmail();

        $token = $user->createToken('mercova')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado con éxito.',
            'token' => $token,
            'user' => $user,
            'subscription' => $this->subscriptions->summaryFor($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Credenciales inválidas.',
            ], 401);
        }

        $user = Auth::user();
        $user->syncRoleFromAdminEmailList();

        if (! $user->is_active) {
            Auth::logout();

            return response()->json([
                'message' => 'Tu cuenta ha sido desactivada. Contacta a un administrador.',
            ], 403);
        }

        $token = $user->createToken('mercova')->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión correcto.',
            'token' => $token,
            'user' => $user,
            'subscription' => $this->subscriptions->summaryFor($user),
        ]);
    }

    /**
     * Último paso del login con Google/Apple: canjea el código de un solo
     * uso con el que el callback redirigió al frontend por un token Sanctum.
     * Responde igual que /api/login.
     */
    public function socialExchange(Request $request, SocialLoginCodes $loginCodes)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:64'],
        ]);

        $user = $loginCodes->redeem($validated['code']);

        if (! $user) {
            return response()->json([
                'message' => 'El enlace de inicio de sesión no es válido o expiró. Intenta de nuevo.',
            ], 422);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Tu cuenta ha sido desactivada. Contacta a un administrador.',
            ], 403);
        }

        return response()->json([
            'message' => 'Inicio de sesión correcto.',
            'token' => $user->createToken('mercova')->plainTextToken,
            'user' => $user,
            'subscription' => $this->subscriptions->summaryFor($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load(['products', 'services', 'orders.items.product', 'bookings']),
            'subscription' => $this->subscriptions->summaryFor($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'avatar_url' => ['sometimes', 'nullable', 'url'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'user' => $user->fresh(),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'La contraseña actual no es correcta.',
            ], 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'No pudimos enviar el enlace de recuperación.',
            ], 422);
        }

        return response()->json([
            'message' => 'Te enviamos un enlace para recuperar tu contraseña.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->update(['password' => Hash::make($password)]);
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'El token de recuperación no es válido o expiró.',
            ], 422);
        }

        return response()->json([
            'message' => 'Contraseña restablecida correctamente. Ya puedes iniciar sesión.',
        ]);
    }
}
