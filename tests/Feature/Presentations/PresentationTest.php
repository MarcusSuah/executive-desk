<?php

use App\Enums\PresentationStatus;
use App\Models\Attachment;
use App\Models\Presentation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-26 01:00:00', 'UTC'));
    Storage::fake('local');
    $this->assistant = User::factory()->assistant()->create();
    $this->executive = User::factory()->executive()->create();
    $this->presentation = Presentation::factory()->for($this->executive, 'owner')->create();
});

describe('managing presentations', function () {
    it('creates a presentation with deadline reminders', function () {
        $this->actingAs($this->assistant)->post(route('presentations.store'), [
            'owner_id' => $this->executive->id,
            'title' => 'Series B Investor Update',
            'timezone' => 'UTC',
            'presentation_at' => '2026-11-10T10:00',
            'preparation_deadline_at' => '2026-11-06T17:00',
            'review_deadline_at' => '2026-11-08T17:00',
            'status' => 'in_preparation',
        ])->assertSessionHasNoErrors();

        $presentation = Presentation::where('title', 'Series B Investor Update')->sole();

        expect($presentation->reminders()->pluck('kind')->map->value->unique()->sort()->values()->all())
            ->toBe(['presentation_preparation', 'presentation_review']);
    });

    it('rejects a preparation deadline after the presentation', function () {
        $this->actingAs($this->assistant)->post(route('presentations.store'), [
            'owner_id' => $this->executive->id,
            'title' => 'Series B Investor Update',
            'timezone' => 'UTC',
            'presentation_at' => '2026-11-10T10:00',
            'preparation_deadline_at' => '2026-11-11T10:00',
            'status' => 'draft',
        ])->assertSessionHasErrors(['preparation_deadline_at' => 'The preparation deadline must be before the presentation.']);
    });

    it('lets the owning executive approve their presentation', function () {
        $this->actingAs($this->executive)
            ->post(route('presentations.approve', $this->presentation))
            ->assertRedirect();

        expect($this->presentation->refresh()->status)->toBe(PresentationStatus::Approved)
            ->and($this->presentation->approved_by)->toBe($this->executive->id);
    });

    it('forbids another executive from viewing the presentation', function () {
        $this->actingAs(User::factory()->executive()->create())
            ->get(route('presentations.show', $this->presentation))
            ->assertForbidden();
    });
});

describe('presentation files', function () {
    it('stores an upload privately and versions files with the same name', function () {
        foreach ([1, 2] as $ignored) {
            $this->actingAs($this->assistant)
                ->post(route('presentations.attachments.store', $this->presentation), [
                    'file' => UploadedFile::fake()->create('slides.pdf', 200, 'application/pdf'),
                ])
                ->assertSessionHasNoErrors();
        }

        $attachments = Attachment::orderBy('version')->get();

        expect($attachments->pluck('version')->all())->toBe([1, 2])
            ->and($attachments->first()->path)->not->toContain('slides.pdf');
        Storage::disk('local')->assertExists($attachments->first()->path);
    });

    it('rejects executable file types', function () {
        $this->actingAs($this->assistant)
            ->post(route('presentations.attachments.store', $this->presentation), [
                'file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
            ])
            ->assertSessionHasErrors('file');

        expect(Attachment::count())->toBe(0);
    });

    it('rejects files over the size limit', function () {
        config(['executive-desk.uploads.max_kilobytes' => 100]);

        $this->actingAs($this->assistant)
            ->post(route('presentations.attachments.store', $this->presentation), [
                'file' => UploadedFile::fake()->create('slides.pdf', 500, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');
    });

    it('lets the owner download a file as an attachment', function () {
        $this->actingAs($this->assistant)->post(route('presentations.attachments.store', $this->presentation), [
            'file' => UploadedFile::fake()->create('slides.pdf', 20, 'application/pdf'),
        ]);

        $this->actingAs($this->executive)
            ->get(route('attachments.download', Attachment::sole()))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertDownload('slides.pdf');
    });

    it('forbids downloads by users who cannot see the presentation', function () {
        $this->actingAs($this->assistant)->post(route('presentations.attachments.store', $this->presentation), [
            'file' => UploadedFile::fake()->create('slides.pdf', 20, 'application/pdf'),
        ]);

        $this->actingAs(User::factory()->executive()->create())
            ->get(route('attachments.download', Attachment::sole()))
            ->assertForbidden();
    });

    it('requires authentication to download a file', function () {
        $attachment = Attachment::factory()->create(['attachable_id' => $this->presentation->id]);

        $this->get(route('attachments.download', $attachment))->assertRedirect(route('login'));
    });
});
