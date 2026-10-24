<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\ChecklistItem;
use App\Models\Presentation;
use App\Models\Task;
use App\Models\User;

class ChecklistManager
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Add an item to the end of a record's checklist.
     */
    public function add(CalendarEvent|Task|Presentation $checklistable, string $title, User $actor): ChecklistItem
    {
        $item = $checklistable->checklistItems()->create([
            'title' => $title,
            'position' => ((int) $checklistable->checklistItems()->max('position')) + 1,
        ]);

        $this->audit->log('checklist.item_added', $checklistable, [], ['title' => $title], $actor);

        return $item;
    }

    /**
     * Mark an item complete or incomplete.
     */
    public function toggle(ChecklistItem $item, bool $completed, User $actor): ChecklistItem
    {
        if ($item->is_completed === $completed) {
            return $item;
        }

        $item->forceFill([
            'is_completed' => $completed,
            'completed_at' => $completed ? now() : null,
            'completed_by' => $completed ? $actor->id : null,
        ])->save();

        $this->audit->log(
            $completed ? 'checklist.item_completed' : 'checklist.item_reopened',
            $item->checklistable,
            [],
            ['title' => $item->title],
            $actor,
        );

        return $item;
    }

    /**
     * Remove an item.
     */
    public function remove(ChecklistItem $item, User $actor): void
    {
        $item->delete();

        $this->audit->log('checklist.item_removed', $item->checklistable, [], ['title' => $item->title], $actor);
    }
}
