<?php

namespace App\Http\Controllers;

use App\Models\CompanyInvitation;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class TeamInvitationController extends Controller
{
    /**
     * Show the invitation landing page.
     * Detects whether the invited email already has an account.
     */
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = CompanyInvitation::where('token', $token)
            ->with('company:id,name')
            ->firstOrFail();

        if ($invitation->accepted_at || $invitation->expires_at->isPast()) {
            return redirect('/')->with('error', 'This invitation has expired or already been used.');
        }

        $hasAccount = User::where('email', $invitation->email)->exists();

        return Inertia::render('invitations/Accept', [
            'invitation' => [
                'token'      => $invitation->token,
                'email'      => $invitation->email,
                'role'       => $invitation->role,
                'company'    => $invitation->company->only('name'),
                'expires_at' => $invitation->expires_at,
            ],
            'hasAccount' => $hasAccount,
        ]);
    }

    /**
     * Show the "create account to accept" registration form.
     */
    public function showRegister(string $token): Response|RedirectResponse
    {
        $invitation = CompanyInvitation::where('token', $token)
            ->with('company:id,name')
            ->firstOrFail();

        if ($invitation->accepted_at || $invitation->expires_at->isPast()) {
            return redirect('/')->with('error', 'This invitation has expired or already been used.');
        }

        // If user already has an account, send them to login instead
        if (User::where('email', $invitation->email)->exists()) {
            redirect()->setIntendedUrl(route('invitations.show', $token));
            return redirect()->route('login')
                ->with('status', "An account for {$invitation->email} already exists. Please log in to accept your invitation.");
        }

        return Inertia::render('invitations/Register', [
            'invitation' => [
                'token'   => $invitation->token,
                'email'   => $invitation->email,
                'role'    => $invitation->role,
                'company' => $invitation->company->only('name'),
            ],
        ]);
    }

    /**
     * Register a new user from an invitation and accept it immediately.
     */
    public function register(Request $request, string $token): RedirectResponse
    {
        $invitation = CompanyInvitation::where('token', $token)
            ->with('company')
            ->firstOrFail();

        if ($invitation->accepted_at || $invitation->expires_at->isPast()) {
            return redirect('/')->with('error', 'This invitation has expired or already been used.');
        }

        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $invitation->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        $this->attachUserToCompany($invitation, $user);

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', "Welcome to {$invitation->company->name}! Your account has been created.");
    }

    /**
     * Accept the invitation — must be logged in with the matching email.
     * Removes auth middleware from route so we can handle the redirect here.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = CompanyInvitation::where('token', $token)
            ->with('company')
            ->firstOrFail();

        if ($invitation->accepted_at || $invitation->expires_at->isPast()) {
            return redirect('/')->with('error', 'This invitation has expired or already been used.');
        }

        $user = $request->user();

        if (! $user) {
            // No account at all → send to registration
            if (! User::where('email', $invitation->email)->exists()) {
                return redirect()->route('invitations.register', $token);
            }

            // Has account → send to login, redirect()->intended() will bring them back here after
            redirect()->setIntendedUrl(route('invitations.show', $token));
            return redirect()->route('login')
                ->with('status', "Please log in as {$invitation->email} to accept your invitation to {$invitation->company->name}.");
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            return back()->with('error', "You must be logged in as {$invitation->email} to accept this invitation.");
        }

        $this->attachUserToCompany($invitation, $user);

        return redirect()->route('dashboard')
            ->with('success', "Welcome to {$invitation->company->name}!");
    }

    /**
     * Decline the invitation.
     */
    public function decline(string $token): RedirectResponse
    {
        $invitation = CompanyInvitation::where('token', $token)->firstOrFail();
        $invitation->delete();

        return redirect('/')->with('status', 'Invitation declined.');
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function attachUserToCompany(CompanyInvitation $invitation, User $user): void
    {
        if (! CompanyUser::where('company_id', $invitation->company_id)->where('user_id', $user->id)->exists()) {
            CompanyUser::create([
                'company_id' => $invitation->company_id,
                'user_id'    => $user->id,
                'role'       => $invitation->role,
                'invited_by' => $invitation->invited_by,
                'joined_at'  => now(),
                'is_active'  => true,
            ]);
        }

        $invitation->update(['accepted_at' => now()]);
        $user->update(['current_company_id' => $invitation->company_id]);
    }
}
