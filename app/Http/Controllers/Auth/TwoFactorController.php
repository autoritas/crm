<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function setup()
    {
        $user = auth()->user();

        if (!$user->must_enable_2fa && $user->two_factor_confirmed_at) {
            return redirect('/admin');
        }

        $google2fa = new Google2FA();

        if (empty($user->getRawOriginal('two_factor_secret'))) {
            $secret = $google2fa->generateSecretKey();
            $user->forceFill([
                'two_factor_secret' => $this->encryptSecret($secret),
            ])->save();
        } else {
            $secret = $user->decryptTwoFactorSecret();
        }

        $qrUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return view('auth.two-factor-setup', compact('qrUrl', 'secret'));
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = auth()->user();
        $secret = $user->decryptTwoFactorSecret();

        $google2fa = new Google2FA();
        if (!$google2fa->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => __('Codigo incorrecto.')]);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'must_enable_2fa' => false,
        ])->save();

        return redirect('/admin');
    }

    private function encryptSecret(string $secret): string
    {
        $key = config('services.stockflow.app_key');
        if (!$key) {
            return $secret;
        }

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        $encrypter = new Encrypter($key, config('app.cipher'));
        return $encrypter->encrypt($secret);
    }
}
