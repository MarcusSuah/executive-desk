<?php

namespace App\Services\Presentations;

use App\Enums\PresentationStatus;
use App\Models\Presentation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Reminders\ReminderPlanner;
use Illuminate\Support\Facades\DB;

class PresentationManager
{
    public function __construct(
        private ReminderPlanner $reminders,
        private AuditLogger $audit,
    ) {}

    /**
     * Create a presentation.
     *
     * @param  array<string, mixed>  $attributes  Validated attributes with UTC timestamps.
     * @param  array<int, string>  $checklist
     */
    public function create(array $attributes, array $checklist, User $actor): Presentation
    {
        return DB::transaction(function () use ($attributes, $checklist, $actor): Presentation {
            $presentation = new Presentation($attributes);
            $presentation->created_by = $actor->id;
            $presentation->save();

            foreach (array_values($checklist) as $position => $title) {
                $presentation->checklistItems()->create(['title' => $title, 'position' => $position]);
            }

            $this->audit->log('presentation.created', $presentation, [], $presentation->only([
                'title', 'owner_id', 'presenter_id', 'presentation_at', 'preparation_deadline_at', 'status',
            ]), $actor);

            $this->reminders->syncPresentation($presentation);

            return $presentation;
        });
    }

    /**
     * Update a presentation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Presentation $presentation, array $attributes, User $actor): Presentation
    {
        return DB::transaction(function () use ($presentation, $attributes, $actor): Presentation {
            $presentation->fill($attributes);

            if ($presentation->isDirty('status') && $presentation->status !== PresentationStatus::Approved) {
                $presentation->approved_at = null;
                $presentation->approved_by = null;
            }

            $presentation->save();

            $this->audit->logChanges('presentation.updated', $presentation, $actor);

            $this->reminders->syncPresentation($presentation);

            return $presentation;
        });
    }

    /**
     * Approve a presentation.
     */
    public function approve(Presentation $presentation, User $actor): Presentation
    {
        return DB::transaction(function () use ($presentation, $actor): Presentation {
            $previous = $presentation->status;

            $presentation->forceFill([
                'status' => PresentationStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            $this->audit->log('presentation.approved', $presentation, ['status' => $previous->value], ['status' => PresentationStatus::Approved->value], $actor);

            $this->reminders->syncPresentation($presentation);

            return $presentation;
        });
    }

    /**
     * Soft delete a presentation.
     */
    public function delete(Presentation $presentation, User $actor): void
    {
        DB::transaction(function () use ($presentation, $actor): void {
            $this->reminders->cancelAll($presentation, 'Presentation deleted');
            $presentation->delete();

            $this->audit->log('presentation.deleted', $presentation, $presentation->only(['title']), [], $actor);
        });
    }
}
