<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteRegistrationRequest;
use App\Http\Requests\Auth\InitiateRegistrationRequest;
use App\Models\User;
use App\Notifications\DuplicateRegistrationAttemptNotification;
use App\Notifications\RegistrationVerificationNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    /**
     * Step 1: Show email-only registration form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Step 1: Process email — send verification or alert, always redirect to success.
     */
    public function store(InitiateRegistrationRequest $request): RedirectResponse
    {
        $email = config('fortify.lowercase_usernames')
            ? Str::lower($request->validated('email'))
            : $request->validated('email');

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            $existingUser->notify(new DuplicateRegistrationAttemptNotification($request->ip()));
        } else {
            $signedUrl = URL::temporarySignedRoute(
                'register.complete',
                now()->addMinutes(120),
                ['email' => $email]
            );

            Notification::route('mail', $email)
                ->notify(new RegistrationVerificationNotification($signedUrl));
        }

        return redirect()->route('register.success');
    }

    /**
     * Generic success page — shown for both new and existing emails.
     */
    public function success(): Response
    {
        return Inertia::render('Auth/RegisterSuccess');
    }

    /**
     * Step 2: Show full registration form (email pre-filled, read-only).
     * Route has 'signed' middleware — invalid/expired signatures are rejected automatically.
     */
    public function complete(Request $request): Response|RedirectResponse
    {
        $email = $request->query('email');

        if (User::where('email', $email)->exists()) {
            return redirect()->route('login')
                ->with('status', 'You already have an account. Please log in.');
        }

        return Inertia::render('Auth/RegisterComplete', [
            'email' => $email,
            'signedUrl' => $request->fullUrl(),
        ]);
    }

    /**
     * Step 2: Create the user account and log in.
     */
    public function storeComplete(CompleteRegistrationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $email = config('fortify.lowercase_usernames')
            ? Str::lower($validated['email'])
            : $validated['email'];

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $email,
                'password' => Hash::make($validated['password']),
            ]);

            $user->markEmailAsVerified();

            event(new Registered($user));

            Auth::login($user);

            $request->session()->regenerate();

            return redirect()->route('dashboard');
        } catch (UniqueConstraintViolationException) {
            return redirect()->route('login')
                ->with('status', 'You already have an account. Please log in.');
        }
    }
}
