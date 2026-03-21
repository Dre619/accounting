<?php

namespace App\Http\Controllers;

use App\Models\CompanyInvitation;
use App\Models\CompanyUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamInvitationController extends Controller
{
    /**
     * Show the invitation acceptance page.
     */
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = CompanyInvitation::where('token', $token)
            ->with('company:id,name')
            ->firstOrFail();

        if ($invitation->accepted_at || $invitation->expires_at->isPast()) {
            return redirect('/')->with('error', 'This invitation has expired or already been used.');
        }

        return Inertia::render('invitations/Accept', [
            'invitation' => [
                'token'       => $invitation->token,
                'email'       => $invitation->email,
                'role'        => $invitation->role,
                'company'     => $invitation->company->only('name'),
                'expires_at'  => $invitation->expires_at,
            ],
        ]);
    }

    /**
     * Accept the invitation — user must be logged in with matching email.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = CompanyInvitation::where('token', $token)->firstOrFail();

        if ($invitation->accepted_at || $invitation->expires_at->isPast()) {
            return redirect('/')->with('error', 'This invitation has expired or already been used.');
        }

        $user = $request->user();

        if (! $user) {
            // Store token in session and redirect to login
            session(['invitation_token' => $token]);
            return redirect()->route('login')->with('status', "Please log in to accept your invitation to {$invitation->company->name}.");
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            return back()->with('error', "You must be logged in as {$invitation->email} to accept this invitation.");
        }

        // Already a member?
        if (CompanyUser::where('company_id', $invitation->company_id)->where('user_id', $user->id)->exists()) {
            $invitation->update(['accepted_at' => now()]);
            $user->update(['current_company_id' => $invitation->company_id]);
            return redirect()->route('dashboard')->with('success', 'Welcome back!');
        }

        // Add as member
        CompanyUser::create([
            'company_id'  => $invitation->company_id,
            'user_id'     => $user->id,
            'role'        => $invitation->role,
            'invited_by'  => $invitation->invited_by,
            'joined_at'   => now(),
            'is_active'   => true,
        ]);

        $invitation->update(['accepted_at' => now()]);
        $user->update(['current_company_id' => $invitation->company_id]);

        return redirect()->route('dashboard')
            ->with('success', "Welcome to {$invitation->company->name}!");
    }
}
