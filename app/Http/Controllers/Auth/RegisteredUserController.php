<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LandlordWelcome;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->role = 'tenant';
        $user->save();

        event(new Registered($user));

        // HANDLE-6: queue welcome mail so an SMTP hiccup doesn't 500 the
        // registration response.
        if ($user->role === 'landlord') {
            Mail::to($user)->queue(new LandlordWelcome($user));
        }

        Auth::login($user);

        // CRYPTO-5: rotate the session id across the privilege transition
        // so a fixated cookie can't ride the registration flow.
        $request->session()->regenerate();

        // Redirect logic based on role
        // Tenants/Caretakers don't need onboarding wizard
        if ($user->role === 'landlord') {
            return redirect(route('dashboard', absolute: false));
        } else {
            // For now send everyone to dashboard, but later tenants go to Portal
            return redirect(route('dashboard', absolute: false));
        }
    }
}
