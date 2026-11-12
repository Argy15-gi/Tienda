<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegistroRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no coinciden con nuestros registros.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()
            ->intended(route('panel'))
            ->with('status', 'Bienvenido de nuevo, '.($request->user()->name ?? $request->user()->email).'.');
    }

    public function demoLogin(Request $request): RedirectResponse
    {
        abort_unless((bool) config('services.demo.enabled', false), 403, 'El acceso rápido de demostración está deshabilitado.');

        $data = $request->validate([
            'rol' => ['required', 'string', 'in:ADMIN,VENDEDOR,COMPRADOR'],
        ]);

        $emailMap = [
            'ADMIN' => 'admin@tiendita.test',
            'VENDEDOR' => 'vendedor@tiendita.test',
            'COMPRADOR' => 'comprador@tiendita.test',
        ];

        $user = User::query()->where('email', $emailMap[$data['rol']])->firstOrFail();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('panel')
            ->with('status', 'Bienvenido, '.($user->name ?? $user->email).' (perfil '.$user->rol.').');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegistroRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            $nuevo = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $nuevo->forceFill([
                'rol' => 'COMPRADOR',
                'email_verified_at' => now(),
            ])->save();

            $nuevo->registros()->create([
                'accion' => 'USUARIO_REGISTRADO',
                'detalle' => json_encode([
                    'email' => $nuevo->email,
                    'rol' => $nuevo->rol,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);

            return $nuevo;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('panel')
            ->with('status', 'Tu cuenta fue creada con éxito. ¡Bienvenido a La Tiendita!');
    }

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $clientId = config('services.google.client_id');
        abort_unless($clientId && config('services.google.client_secret'), 503, 'Configura GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET para habilitar Google OAuth.');

        $state = Str::random(48);
        $request->session()->put('google_oauth_state', $state);
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_oauth_state');
        $state = $request->query('state');
        abort_unless(is_string($expectedState) && is_string($state) && hash_equals($expectedState, $state), 419, 'La solicitud OAuth expiró o no es válida.');
        abort_if($request->filled('error'), 403, 'Google rechazó el inicio de sesión.');
        $request->validate(['code' => ['required', 'string']]);

        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $request->string('code')->toString(),
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        abort_unless(is_array($tokenResponse), 502, 'Google devolvió una respuesta de token inválida.');
        $accessToken = $tokenResponse['access_token'] ?? null;
        abort_unless(is_string($accessToken) && $accessToken !== '', 401, 'Google no devolvió un token de acceso válido.');

        $googleUser = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v3/userinfo')
            ->throw()
            ->json();

        abort_unless(is_array($googleUser), 502, 'Google devolvió un perfil inválido.');
        abort_unless(
            is_string($googleUser['sub'] ?? null)
                && is_string($googleUser['email'] ?? null)
                && filter_var($googleUser['email'], FILTER_VALIDATE_EMAIL)
                && ($googleUser['email_verified'] ?? false) === true,
            401,
            'Google no devolvió una cuenta verificada válida.',
        );

        $user = DB::transaction(function () use ($googleUser): User {
            $user = User::query()->where('google_id', $googleUser['sub'])->first();
            if ($user) {
                $user->forceFill([
                    'name' => $googleUser['name'] ?? $user->name,
                    'email' => $googleUser['email'],
                    'email_verified_at' => now(),
                ])->save();

                return $user;
            }

            $user = User::query()->where('email', $googleUser['email'])->first();
            if ($user) {
                abort_if($user->google_id !== null, 409, 'El correo está asociado a otra cuenta de Google.');
                $user->forceFill(['google_id' => $googleUser['sub']])->save();

                return $user;
            }

            $nuevo = new User([
                'name' => $googleUser['name'] ?? null,
                'email' => $googleUser['email'],
            ]);
            $nuevo->forceFill([
                'google_id' => $googleUser['sub'],
                'rol' => 'COMPRADOR',
                'email_verified_at' => now(),
            ])->save();

            return $nuevo;
        });

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('catalogo')->with('status', 'Sesión iniciada.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('catalogo')->with('status', 'Has cerrado sesión correctamente.');
    }
}
