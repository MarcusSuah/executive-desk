<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Notifications\PhoneVerifier;
use App\Services\Reminders\ReminderPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PhoneVerificationController extends Controller
{
    public function __construct(private PhoneVerifier $verifier) {}

    /**
     * Send a verification code to a mobile number.
     */
    public function send(Request $request): RedirectResponse
    {
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => preg_replace('/[\s\-().]/', '', $request->input('phone'))]);
        }

        $validated = $request->validate(
            ['phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/']],
            ['phone.regex' => 'Enter the number in international format, e.g. +639171234567.'],
        );

        $this->verifier->sendCode($request->user(), $validated['phone']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Verification code sent.')]);

        return back();
    }

    /**
     * Confirm the code and mark the number verified.
     */
    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);

        $this->verifier->verify($request->user(), $validated['code']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mobile number verified.')]);

        return back();
    }

    /**
     * Remove the mobile number and turn SMS notifications off.
     */
    public function destroy(Request $request, ReminderPlanner $planner, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill(['phone' => null, 'phone_verified_at' => null])->save();
        $user->preferences()->update(['sms_enabled' => false]);

        $audit->log('phone.removed', $user, [], [], $user);
        $planner->syncForUser($user->refresh());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mobile number removed.')]);

        return back();
    }
}
