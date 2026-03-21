<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mail\TeamInvitation as TeamInvitationMail;
use App\Models\CompanyInvitation;
use App\Models\CompanyUser;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany;

        $members = $company->members()
            ->wherePivot('is_active', true)
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn ($u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'email'     => $u->email,
                'role'      => $u->pivot->role,
                'joined_at' => $u->pivot->joined_at,
            ]);

        // Owner entry
        $owner = $company->owner;
        $allMembers = collect([[
            'id'        => $owner->id,
            'name'      => $owner->name,
            'email'     => $owner->email,
            'role'      => 'owner',
            'joined_at' => $company->created_at,
        ]])->concat($members);

        $pendingInvitations = $company->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get(['id', 'email', 'role', 'created_at', 'expires_at']);

        // Plan limits
        $subscription = $company->activeSubscription;
        $planMaxUsers = $subscription?->plan?->max_users ?? 1;
        $currentCount = $company->userCount();

        return Inertia::render('settings/Team', [
            'members'            => $allMembers,
            'pendingInvitations' => $pendingInvitations,
            'planMaxUsers'       => $planMaxUsers,
            'currentCount'       => $currentCount,
            'canInvite'          => $currentCount < $planMaxUsers,
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $company = $request->user()->currentCompany;

        // Check plan limit
        $subscription = $company->activeSubscription;
        $planMaxUsers = $subscription?->plan?->max_users ?? 1;

        if ($company->userCount() >= $planMaxUsers) {
            return back()->withErrors(['email' => "Your plan allows a maximum of {$planMaxUsers} user(s). Please upgrade to invite more."]);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'role'  => ['required', 'in:admin,member,viewer'],
        ]);

        // Don't re-invite if already a member
        if ($company->members()->where('users.email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'This person is already a member of this company.']);
        }

        // Upsert invitation (replace if previously expired/rejected)
        $company->invitations()->where('email', $data['email'])->delete();

        $invitation = $company->invitations()->create([
            'email'       => $data['email'],
            'role'        => $data['role'],
            'token'       => Str::random(64),
            'invited_by'  => $request->user()->id,
            'expires_at'  => now()->addDays(7),
        ]);

        Mail::to($data['email'])->send(new TeamInvitationMail($invitation));

        return back()->with('success', "Invitation sent to {$data['email']}.");
    }

    public function resend(Request $request, CompanyInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->company_id === $request->user()->currentCompany->id, 403);

        $invitation->update([
            'token'      => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($invitation->email)->send(new TeamInvitationMail($invitation));

        return back()->with('success', "Invitation resent to {$invitation->email}.");
    }

    public function cancelInvitation(Request $request, CompanyInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->company_id === $request->user()->currentCompany->id, 403);
        $invitation->delete();

        return back()->with('success', 'Invitation cancelled.');
    }

    public function updateRole(Request $request, int $userId): RedirectResponse
    {
        $company = $request->user()->currentCompany;
        abort_if($userId === $company->user_id, 403, "Cannot change the owner's role.");

        $data = $request->validate(['role' => ['required', 'in:admin,member,viewer']]);

        CompanyUser::where('company_id', $company->id)
            ->where('user_id', $userId)
            ->update(['role' => $data['role']]);

        return back()->with('success', 'Role updated.');
    }

    public function remove(Request $request, int $userId): RedirectResponse
    {
        $company = $request->user()->currentCompany;
        abort_if($userId === $company->user_id, 403, 'Cannot remove the company owner.');
        abort_if($userId === $request->user()->id, 403, 'You cannot remove yourself.');

        CompanyUser::where('company_id', $company->id)
            ->where('user_id', $userId)
            ->delete();

        return back()->with('success', 'Team member removed.');
    }
}
