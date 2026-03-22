<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    // ── Admin Profile ─────────────────────────────────────────────────────────

    public function profile(Request $request)
    {
        return Inertia::render('admin/settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status'          => $request->session()->get('status'),
        ]);
    }

    public function security()
    {
        return Inertia::render('admin/settings/Security');
    }

    public function appearance()
    {
        return Inertia::render('admin/settings/Appearance');
    }

    // ── Platform settings ─────────────────────────────────────────────────────

    public function platform()
    {
        return Inertia::render('admin/settings/Platform', [
            'lenco' => [
                'public_key'  => config('lenco.public_key'),
                'secret_key'  => config('lenco.secret_key'),
                'base_url'    => config('lenco.base_url'),
            ],
            'mail' => [
                'mailer'       => config('mail.default'),
                'host'         => config('mail.mailers.smtp.host'),
                'port'         => config('mail.mailers.smtp.port'),
                'username'     => config('mail.mailers.smtp.username'),
                'from_address' => config('mail.from.address'),
                'from_name'    => config('mail.from.name'),
            ],
            'banking' => [
                'bank_name'      => config('banking.bank_name'),
                'account_name'   => config('banking.account_name'),
                'account_number' => config('banking.account_number'),
                'branch'         => config('banking.branch'),
                'swift_code'     => config('banking.swift_code'),
                'sort_code'      => config('banking.sort_code'),
                'mobile_money'   => config('banking.mobile_money'),
                'instructions'   => config('banking.instructions'),
            ],
        ]);
    }

    public function updatePlatform(Request $request)
    {
        $data = $request->validate([
            'lenco_public_key'   => 'nullable|string|max:200',
            'lenco_secret_key'   => 'nullable|string|max:200',
            'lenco_base_url'     => 'nullable|url|max:200',
            'mail_mailer'        => 'nullable|string|max:50',
            'mail_host'          => 'nullable|string|max:200',
            'mail_port'          => 'nullable|integer',
            'mail_username'      => 'nullable|string|max:200',
            'mail_password'      => 'nullable|string|max:200',
            'mail_from_address'  => 'nullable|email|max:200',
            'mail_from_name'     => 'nullable|string|max:200',
            'bank_name'          => 'nullable|string|max:200',
            'bank_account_name'  => 'nullable|string|max:200',
            'bank_account_number'=> 'nullable|string|max:100',
            'bank_branch'        => 'nullable|string|max:200',
            'bank_swift_code'    => 'nullable|string|max:50',
            'bank_sort_code'     => 'nullable|string|max:50',
            'bank_mobile_money'  => 'nullable|string|max:200',
            'bank_instructions'  => 'nullable|string|max:500',
        ]);

        $env = base_path('.env');
        $content = file_get_contents($env);

        $map = [
            'LENCO_PUBLIC_KEY'   => $data['lenco_public_key']    ?? '',
            'LENCO_SECRET_KEY'   => $data['lenco_secret_key']    ?? '',
            'LENCO_BASE_URL'     => $data['lenco_base_url']      ?? 'https://api.lenco.co/access/v1',
            'MAIL_MAILER'        => $data['mail_mailer']         ?? 'log',
            'MAIL_HOST'          => $data['mail_host']           ?? '',
            'MAIL_PORT'          => $data['mail_port']           ?? 587,
            'MAIL_USERNAME'      => $data['mail_username']       ?? '',
            'MAIL_FROM_ADDRESS'  => $data['mail_from_address']   ?? '',
            'MAIL_FROM_NAME'     => '"' . ($data['mail_from_name'] ?? '') . '"',
            'BANK_NAME'          => '"' . ($data['bank_name']          ?? '') . '"',
            'BANK_ACCOUNT_NAME'  => '"' . ($data['bank_account_name']  ?? '') . '"',
            'BANK_ACCOUNT_NUMBER'=> $data['bank_account_number'] ?? '',
            'BANK_BRANCH'        => '"' . ($data['bank_branch']        ?? '') . '"',
            'BANK_SWIFT_CODE'    => $data['bank_swift_code']     ?? '',
            'BANK_SORT_CODE'     => $data['bank_sort_code']      ?? '',
            'BANK_MOBILE_MONEY'  => '"' . ($data['bank_mobile_money']  ?? '') . '"',
            'BANK_INSTRUCTIONS'  => '"' . ($data['bank_instructions']  ?? 'Use your company name as the payment reference.') . '"',
        ];

        foreach ($map as $key => $value) {
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
            } else {
                $content .= "\n{$key}={$value}";
            }
        }

        file_put_contents($env, $content);

        return back()->with('success', 'Platform settings saved. Restart the server to apply env changes.');
    }

    // ── Subscription Plans ────────────────────────────────────────────────────

    public function plans()
    {
        return Inertia::render('admin/settings/Plans', [
            'plans' => SubscriptionPlan::orderBy('sort_order')->get(),
        ]);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'description'   => 'nullable|string|max:500',
            'price_monthly' => 'required|numeric|min:0',
            'price_annual'  => 'required|numeric|min:0',
            'max_users'     => 'nullable|integer|min:1',
            'features'      => 'nullable|array',
            'features.*'    => 'string|max:200',
            'is_active'     => 'boolean',
            'sort_order'    => 'integer|min:0',
        ]);

        $plan->update($data);

        return back()->with('success', "Plan \"{$plan->name}\" updated.");
    }

    // ── Users ─────────────────────────────────────────────────────────────────

    public function users()
    {
        return Inertia::render('admin/settings/Users', [
            'users' => User::select('id', 'name', 'email', 'is_admin', 'created_at')
                ->withCount('companies')
                ->latest()
                ->paginate(25),
        ]);
    }

    public function toggleAdmin(Request $request, User $user)
    {
        // Prevent removing own admin
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot change your own admin status.']);
        }

        $user->update(['is_admin' => !$user->is_admin]);

        return back()->with('success', "Admin status updated for {$user->name}.");
    }

    public function destroyUser(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account here.']);
        }

        $user->delete();

        return back()->with('success', "User {$user->email} deleted.");
    }
}
