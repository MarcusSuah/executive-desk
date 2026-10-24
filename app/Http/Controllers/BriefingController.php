<?php

namespace App\Http\Controllers;

use App\Enums\BriefingStatus;
use App\Enums\BriefingType;
use App\Enums\Permission;
use App\Http\Resources\ExecutiveBriefingResource;
use App\Jobs\SendBriefing;
use App\Models\ExecutiveBriefing;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Briefings\BriefingGenerator;
use App\Support\FormOptions;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BriefingController extends Controller
{
    /**
     * List stored briefings.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canManage = $user->can(Permission::ManageBriefings->value);

        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(BriefingType::class)],
            'user' => ['nullable', 'integer'],
        ]);

        $recipientId = $canManage && isset($filters['user']) ? (int) $filters['user'] : null;

        $briefings = ExecutiveBriefing::query()
            ->with('user:id,name,email,timezone')
            ->when(! $canManage, fn (Builder $query) => $query->where('user_id', $user->id))
            ->when($recipientId, fn (Builder $query, $id) => $query->where('user_id', $id))
            ->when($filters['type'] ?? null, fn (Builder $query, $type) => $query->where('type', $type))
            ->latest('period_start')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('briefings/index', [
            'briefings' => ExecutiveBriefingResource::collection($briefings),
            'filters' => [
                'type' => $filters['type'] ?? '',
                'user' => $recipientId !== null ? (string) $recipientId : '',
            ],
            'recipients' => $canManage ? FormOptions::users() : [],
        ]);
    }

    /**
     * Show a briefing.
     */
    public function show(ExecutiveBriefing $briefing): Response
    {
        Gate::authorize('view', $briefing);

        return Inertia::render('briefings/show', [
            'briefing' => new ExecutiveBriefingResource($briefing->load('user')),
        ]);
    }

    /**
     * Generate (or refresh) the current period's briefing without sending it.
     */
    public function generate(Request $request, BriefingGenerator $generator, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(BriefingType::class)],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $recipient = isset($validated['user_id']) ? User::query()->findOrFail($request->integer('user_id')) : $request->user();

        Gate::authorize('generateFor', [ExecutiveBriefing::class, $recipient]);

        $date = isset($validated['date']) ? CarbonImmutable::parse($validated['date'], $recipient->timezone) : null;
        $briefing = $generator->generate($recipient, BriefingType::from($validated['type']), $date, refresh: true);

        $audit->log('briefing.generated', $briefing, [], ['type' => $briefing->type->value, 'recipient_id' => $recipient->id], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Briefing prepared from the latest data.')]);

        return to_route('briefings.show', $briefing);
    }

    /**
     * Send a briefing now on the recipient's chosen channels. Channels that already
     * succeeded for this briefing are not sent again.
     */
    public function send(Request $request, ExecutiveBriefing $briefing, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('generateFor', [ExecutiveBriefing::class, $briefing->user]);

        $briefing->forceFill(['status' => BriefingStatus::Delivering])->save();
        SendBriefing::dispatch($briefing->id);

        $audit->log('briefing.sent', $briefing, [], [], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Briefing queued for delivery.')]);

        return back();
    }

    /**
     * Download a briefing as a PDF.
     */
    public function pdf(Request $request, ExecutiveBriefing $briefing, AuditLogger $audit): HttpResponse
    {
        Gate::authorize('view', $briefing);

        $briefing->load('user');
        $audit->log('export.briefing_pdf', $briefing, [], [], $request->user());

        $timezone = $briefing->timezone;

        return Pdf::loadView('pdf.briefing', [
            'briefing' => $briefing,
            'format' => fn (?string $iso, string $pattern = 'D, M j g:i A'): string => $iso === null
                ? ''
                : CarbonImmutable::parse($iso)->setTimezone($timezone)->format($pattern),
        ])->download("briefing-{$briefing->type->value}-{$briefing->period_start->toDateString()}.pdf");
    }
}
