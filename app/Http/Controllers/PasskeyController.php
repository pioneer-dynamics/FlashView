<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use PioneerDynamics\LaravelPasskey\Contracts\PasskeyAuthenticator;
use PioneerDynamics\LaravelPasskey\Contracts\PasskeyRegistrar;
use PioneerDynamics\LaravelPasskey\Http\Controllers\PasskeyController as BasePasskeyController;

class PasskeyController extends BasePasskeyController
{
    /**
     * Get registration options for passkey creation.
     *
     * Fixed version that ensures session data is properly persisted
     * to prevent race conditions with database session driver.
     */
    public function getRegistrationOptions(PasskeyRegistrar $passkeyRegistrar, Request $request)
    {
        $options = $passkeyRegistrar->setUser($request->user())->generateOptions();

        // Force save the session to ensure WebAuthn library's session data is persisted
        // This is critical when using database session driver, as the WebAuthn library
        // uses native PHP $_SESSION which needs to be explicitly saved
        $request->session()->save();

        // Return redirect with flash data properly set
        return redirect()->back()->with('flash', [
            'options' => $options
        ]);
    }

    /**
     * Get authentication options for passkey login.
     *
     * Fixed version that ensures session data is properly persisted
     * to prevent race conditions with database session driver.
     */
    public function getAuthenticationOptions(PasskeyAuthenticator $passkeyAuthenticator, Request $request)
    {
        $usernameField = Config::get('passkey.database.username');
        $username = optional($request->user())->$usernameField;

        $user = $username
            ? Config::get('passkey.models.user')::where($usernameField, $username)->first()
            : null;

        $options = $passkeyAuthenticator
            ->setUser($user)
            ->generateOptions();

        // Force save the session to ensure WebAuthn library's session data is persisted
        // This is critical when using database session driver, as the WebAuthn library
        // uses native PHP $_SESSION which needs to be explicitly saved
        $request->session()->save();

        // Return redirect with flash data properly set
        return redirect()->back()->with('flash', [
            'options' => $options
        ]);
    }
}
