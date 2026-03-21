<x-mail::message>
# You've been invited to join {{ $invitation->company->name }}

**{{ $invitation->invitedBy->name }}** has invited you to join **{{ $invitation->company->name }}** as a **{{ $invitation->role }}**.

<x-mail::button :url="url('/invitations/' . $invitation->token)">
Accept Invitation
</x-mail::button>

This invitation expires on **{{ $invitation->expires_at->format('d M Y') }}**.

If you did not expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
