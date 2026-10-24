<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\ChecklistItem;
use App\Models\Presentation;
use App\Models\Task;
use App\Services\ChecklistManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChecklistItemController extends Controller
{
    public function __construct(private ChecklistManager $checklists) {}

    /**
     * Add an item to an event's preparation checklist.
     */
    public function storeForEvent(Request $request, CalendarEvent $event): RedirectResponse
    {
        return $this->store($request, $event);
    }

    /**
     * Add an item to a task's checklist.
     */
    public function storeForTask(Request $request, Task $task): RedirectResponse
    {
        return $this->store($request, $task);
    }

    /**
     * Add an item to a presentation's checklist.
     */
    public function storeForPresentation(Request $request, Presentation $presentation): RedirectResponse
    {
        return $this->store($request, $presentation);
    }

    /**
     * Mark an item complete or incomplete.
     */
    public function update(Request $request, ChecklistItem $item): RedirectResponse
    {
        $this->authorizeParent($request, $item->checklistable);

        $validated = $request->validate(['is_completed' => ['required', 'boolean']]);

        $this->checklists->toggle($item, (bool) $validated['is_completed'], $request->user());

        return back();
    }

    /**
     * Remove an item.
     */
    public function destroy(Request $request, ChecklistItem $item): RedirectResponse
    {
        $this->authorizeParent($request, $item->checklistable);

        $this->checklists->remove($item, $request->user());

        return back();
    }

    /**
     * Validate and add a checklist item.
     */
    private function store(Request $request, CalendarEvent|Task|Presentation $checklistable): RedirectResponse
    {
        $this->authorizeParent($request, $checklistable);

        $validated = $request->validate(['title' => ['required', 'string', 'max:500']]);

        $this->checklists->add($checklistable, $validated['title'], $request->user());

        return back();
    }

    /**
     * Authorize checklist changes using the parent record's policy.
     */
    private function authorizeParent(Request $request, ?Model $parent): void
    {
        $ability = match (true) {
            $parent instanceof CalendarEvent => 'updateChecklist',
            $parent instanceof Task => 'updateProgress',
            $parent instanceof Presentation => 'contribute',
            default => null,
        };

        abort_if($ability === null || ! $request->user()->can($ability, $parent), 403);
    }
}
