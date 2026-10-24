<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\CalendarEvent;
use App\Models\Presentation;
use App\Models\Task;
use App\Services\AttachmentStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(private AttachmentStore $store) {}

    /**
     * Attach a file to an appointment.
     */
    public function storeForEvent(Request $request, CalendarEvent $event): RedirectResponse
    {
        Gate::authorize('attachFiles', $event);

        return $this->upload($request, $event);
    }

    /**
     * Attach a file to a task.
     */
    public function storeForTask(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('updateProgress', $task);

        return $this->upload($request, $task);
    }

    /**
     * Attach a file to a presentation.
     */
    public function storeForPresentation(Request $request, Presentation $presentation): RedirectResponse
    {
        Gate::authorize('contribute', $presentation);

        return $this->upload($request, $presentation);
    }

    /**
     * Download a file the user is allowed to see.
     */
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return $this->store->download($attachment, $request->user());
    }

    /**
     * Delete a file.
     */
    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        Gate::authorize('delete', $attachment);

        $this->store->delete($attachment, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File removed.')]);

        return back();
    }

    /**
     * Validate and store an uploaded file.
     */
    private function upload(Request $request, Model $attachable): RedirectResponse
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.config('executive-desk.uploads.max_kilobytes'),
                'mimes:'.implode(',', config('executive-desk.uploads.mimes')),
                'extensions:'.implode(',', config('executive-desk.uploads.mimes')),
            ],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $this->store->store($attachable, $validated['file'], $request->user(), $validated['label'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File uploaded.')]);

        return back();
    }
}
