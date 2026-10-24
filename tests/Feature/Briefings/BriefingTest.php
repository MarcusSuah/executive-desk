<?php

use App\Enums\BriefingStatus;
use App\Enums\BriefingType;
use App\Jobs\SendBriefing;
use App\Mail\BriefingMail;
use App\Models\CalendarEvent;
use App\Models\ExecutiveBriefing;
use App\Models\NotificationDelivery;
use App\Models\Task;
use App\Models\User;
use App\Services\Briefings\BriefingGenerator;
use App\Services\Briefings\BriefingScheduler;
use App\Services\Notifications\NotificationSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // 07:05 on Monday 26 October 2026 in Manila.
    $this->travelTo(CarbonImmutable::parse('2026-10-25 23:05:00', 'UTC'));
    $this->executive = User::factory()->executive()->create(['timezone' => 'Asia/Manila']);
    $this->executive->preferences()->update([
        'daily_briefing_enabled' => true,
        'daily_briefing_time' => '07:00:00',
        'weekly_briefing_enabled' => true,
        'weekly_briefing_day' => 1,
        'weekly_briefing_time' => '07:00:00',
    ]);
});

/**
 * Find a briefing section by key.
 *
 * @return array<string, mixed>
 */
function briefingSection(ExecutiveBriefing $briefing, string $key): array
{
    return collect($briefing->content['sections'])->firstWhere('key', $key);
}

describe('content', function () {
    it('lists today\'s appointments in order and the overdue and due tasks', function () {
        CalendarEvent::factory()->for($this->executive, 'owner')->create(['title' => 'Late meeting', 'starts_at' => '2026-10-26 07:00:00', 'ends_at' => '2026-10-26 08:00:00']);
        CalendarEvent::factory()->for($this->executive, 'owner')->create(['title' => 'Early meeting', 'starts_at' => '2026-10-26 01:00:00', 'ends_at' => '2026-10-26 02:00:00', 'agenda' => 'Budget']);
        CalendarEvent::factory()->for($this->executive, 'owner')->create(['title' => 'Tomorrow', 'starts_at' => '2026-10-27 01:00:00', 'ends_at' => '2026-10-27 02:00:00']);
        Task::factory()->for($this->executive, 'assignee')->overdue()->create(['title' => 'Overdue report']);
        Task::factory()->for($this->executive, 'assignee')->create(['title' => 'Due today', 'due_at' => '2026-10-26 09:00:00']);

        $briefing = app(BriefingGenerator::class)->generate($this->executive, BriefingType::Daily);

        expect(collect(briefingSection($briefing, 'appointments')['items'])->pluck('title')->all())->toBe(['Early meeting', 'Late meeting'])
            ->and(briefingSection($briefing, 'appointments')['items'][0]['agenda'])->toBe('Budget')
            ->and(collect(briefingSection($briefing, 'overdue')['items'])->pluck('title')->all())->toBe(['Overdue report'])
            ->and(collect(briefingSection($briefing, 'tasks_due')['items'])->pluck('title')->all())->toBe(['Due today'])
            ->and($briefing->period_start->toDateString())->toBe('2026-10-26')
            ->and($briefing->content['summary']['appointments'])->toBe(2);
    });

    it('flags overlapping commitments in the weekly briefing', function () {
        CalendarEvent::factory()->for($this->executive, 'owner')->create(['title' => 'Board', 'starts_at' => '2026-10-27 01:00:00', 'ends_at' => '2026-10-27 03:00:00']);
        CalendarEvent::factory()->for($this->executive, 'owner')->create(['title' => 'Investor call', 'starts_at' => '2026-10-27 02:00:00', 'ends_at' => '2026-10-27 02:30:00']);

        $briefing = app(BriefingGenerator::class)->generate($this->executive, BriefingType::Weekly);

        expect(collect(briefingSection($briefing, 'busy')['items'])->pluck('title')->all())->toContain('Overlap: Board / Investor call')
            ->and($briefing->period_end->toDateString())->toBe('2026-11-01');
    });

    it('returns the existing briefing instead of creating a duplicate', function () {
        $generator = app(BriefingGenerator::class);

        $first = $generator->generate($this->executive, BriefingType::Daily);
        $second = $generator->generate($this->executive, BriefingType::Daily);

        expect($second->id)->toBe($first->id)->and(ExecutiveBriefing::count())->toBe(1);
    });
});

describe('scheduling and delivery', function () {
    it('queues the daily and weekly briefings once their local time has arrived, only once', function () {
        Queue::fake([SendBriefing::class]);
        $scheduler = app(BriefingScheduler::class);

        expect($scheduler->run())->toBe(2)->and($scheduler->run())->toBe(0);
        Queue::assertPushed(SendBriefing::class, 2);
    });

    it('does not queue a briefing before its local time', function () {
        Queue::fake([SendBriefing::class]);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 22:30:00', 'UTC'));

        expect(app(BriefingScheduler::class)->run())->toBe(0);
    });

    it('emails the briefing and does not resend a channel that already succeeded', function () {
        Mail::fake();
        $briefing = app(BriefingGenerator::class)->generate($this->executive, BriefingType::Daily);

        (new SendBriefing($briefing->id))->handle(app(NotificationSender::class));
        (new SendBriefing($briefing->id))->handle(app(NotificationSender::class));

        Mail::assertSent(BriefingMail::class, 1);
        expect($briefing->refresh()->status)->toBe(BriefingStatus::Delivered)
            ->and(NotificationDelivery::where('notification_type', 'daily_briefing')->count())->toBe(1);
    });

    it('renders the briefing email with times in the recipient time zone', function () {
        CalendarEvent::factory()->for($this->executive, 'owner')->create(['title' => 'Strategy review', 'starts_at' => '2026-10-26 02:00:00', 'ends_at' => '2026-10-26 03:00:00']);
        $briefing = app(BriefingGenerator::class)->generate($this->executive, BriefingType::Daily);

        $html = (new BriefingMail($briefing))->render();

        expect($html)->toContain('Strategy review')->toContain('10:00 AM')->toContain('Asia/Manila');
    });
});

describe('access', function () {
    it('forbids another executive from viewing a briefing', function () {
        $briefing = app(BriefingGenerator::class)->generate($this->executive, BriefingType::Daily);

        $this->actingAs(User::factory()->executive()->create())->get(route('briefings.show', $briefing))->assertForbidden();
    });

    it('lets an assistant prepare a briefing for an executive', function () {
        $this->actingAs(User::factory()->assistant()->create())
            ->post(route('briefings.generate'), ['type' => 'daily', 'user_id' => $this->executive->id])
            ->assertRedirect();

        expect(ExecutiveBriefing::where('user_id', $this->executive->id)->exists())->toBeTrue();
    });

    it('forbids an executive from preparing a briefing for someone else', function () {
        $this->actingAs(User::factory()->executive()->create())
            ->post(route('briefings.generate'), ['type' => 'daily', 'user_id' => $this->executive->id])
            ->assertForbidden();
    });

    it('downloads the briefing as a PDF for its owner', function () {
        $briefing = app(BriefingGenerator::class)->generate($this->executive, BriefingType::Daily);

        $this->actingAs($this->executive)
            ->get(route('briefings.pdf', $briefing))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    });
});
