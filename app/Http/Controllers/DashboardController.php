<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\CalendarEvent;
use App\Models\Task;
use App\Services\Dashboard\DashboardSummary;
use App\Support\FormOptions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the executive dashboard.
     */
    public function __invoke(Request $request, DashboardSummary $summary): Response
    {
        $user = $request->user();
        $canFilter = $user->can(Permission::ManageCalendar->value) || $user->can(Permission::ManageTasks->value);

        $validated = $request->validate(['executive' => ['nullable', 'integer', 'exists:users,id']]);
        $executiveId = $canFilter && isset($validated['executive']) ? (int) $validated['executive'] : null;

        return Inertia::render('dashboard', [
            ...$summary->for($user, $executiveId),
            'executive' => $executiveId !== null ? (string) $executiveId : '',
            'executives' => $canFilter ? FormOptions::executives() : [],
            'can' => [
                'createEvent' => $user->can('create', CalendarEvent::class),
                'createTask' => $user->can('create', Task::class),
            ],
        ]);
    }
}
