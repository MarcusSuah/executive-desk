<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Presentation;
use App\Services\AuditLogger;
use App\Support\FormOptions;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'timezones' => FormOptions::timezones(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, AuditLogger $audit): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        $audit->logChanges('profile.updated', $request->user(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        // Appointments and presentations must keep their executive, so these accounts are deactivated by an administrator instead.
        if ($user->ownedEvents()->withTrashed()->exists() || Presentation::withTrashed()->where('owner_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'password' => 'This account owns appointments or presentations, so it cannot be deleted. Ask an administrator to deactivate it instead.',
            ]);
        }

        // Log out first: logging out saves the remember token, which would re-insert a deleted user.
        Auth::logout();

        DB::transaction(function () use ($user, $audit): void {
            $audit->log('user.deleted', $user, ['name' => $user->name, 'email' => $user->email], [], $user);
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
