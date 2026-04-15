<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StockflowAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->intended('/admin');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        /** @var User|null $user */
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('Credenciales no validas.')]);
        }

        if (!$user->is_active) {
            return back()->withErrors(['email' => __('Tu cuenta esta desactivada.')]);
        }

        if ($user->isLocked()) {
            return back()->withErrors(['email' => __('Cuenta bloqueada temporalmente. Intentalo mas tarde.')]);
        }

        if (!Hash::check($request->password, $user->password)) {
            $user->incrementFailedLogin();
            return back()->withErrors(['email' => __('Credenciales no validas.')]);
        }

        if (!$user->hasAppAccess(StockflowAccess::myAppId())) {
            return back()->withErrors(['email' => __('No tienes acceso al CRM.')]);
        }

        if ($user->has2FA()) {
            $request->session()->put('2fa_user_id', $user->id);
            $request->session()->put('2fa_remember', $request->boolean('remember'));
            return redirect()->route('two-factor');
        }

        return $this->completeLogin($user, $request->boolean('remember'), $request);
    }

    public function showTwoFactor(Request $request)
    {
        if (!$request->session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }
        return view('auth.two-factor');
    }

    public function verifyTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $userId = $request->session()->get('2fa_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($userId);
        $secret = $user->decryptTwoFactorSecret();
        if (!$secret) {
            return back()->withErrors(['code' => __('Error verificando el codigo.')]);
        }

        $google2fa = new Google2FA();
        if (!$google2fa->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => __('Codigo incorrecto.')]);
        }

        $remember = $request->session()->get('2fa_remember', false);
        $request->session()->forget(['2fa_user_id', '2fa_remember']);

        return $this->completeLogin($user, $remember, $request);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    private function completeLogin(User $user, bool $remember, Request $request)
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->update(['last_login_at' => now()]);
        $user->resetFailedLogin();

        return redirect()->intended('/admin');
    }
}
