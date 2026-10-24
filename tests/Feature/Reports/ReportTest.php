<?php

use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00:00', 'UTC'));
    $this->executive = User::factory()->executive()->create();
});

it('shows an executive only their own tasks in the completion report', function () {
    Task::factory()->for($this->executive, 'assignee')->create(['title' => 'Mine', 'due_at' => '2026-10-20 09:00:00']);
    Task::factory()->create(['title' => 'Not mine', 'due_at' => '2026-10-20 09:00:00']);

    $this->actingAs($this->executive)
        ->get(route('reports.index', ['report' => 'task_completion', 'from' => '2026-10-01', 'to' => '2026-10-26']))
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->has('report.rows', 1)
            ->where('report.rows.0.title', 'Mine'));
});

it('exports the same rows the user can see as CSV and neutralizes spreadsheet formulas', function () {
    Task::factory()->for($this->executive, 'assignee')->create(['title' => '=HYPERLINK("http://evil")', 'due_at' => '2026-10-20 09:00:00']);
    Task::factory()->create(['title' => 'Other executive task', 'due_at' => '2026-10-20 09:00:00']);

    $response = $this->actingAs($this->executive)
        ->get(route('reports.export', ['report' => 'task_completion', 'from' => '2026-10-01', 'to' => '2026-10-26', 'format' => 'csv']))
        ->assertOk()
        ->assertDownload();

    $csv = file_get_contents($response->baseResponse->getFile()->getPathname());

    expect($csv)->toContain("'=HYPERLINK")->not->toContain('Other executive task');
    $this->assertDatabaseHas('audit_logs', ['action' => 'export.report', 'user_id' => $this->executive->id]);
});

it('exports Excel and PDF files', function (string $format, string $contentType) {
    $this->actingAs($this->executive)
        ->get(route('reports.export', ['report' => 'schedule', 'format' => $format]))
        ->assertOk()
        ->assertHeader('Content-Type', $contentType);
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['pdf', 'application/pdf'],
]);

it('limits the audit report to users who may view audit logs', function () {
    $this->actingAs(User::factory()->assistant()->create())
        ->get(route('reports.export', ['report' => 'audit', 'format' => 'csv']))
        ->assertSessionHasErrors('report');

    $this->actingAs(User::factory()->administrator()->create())
        ->get(route('reports.export', ['report' => 'audit', 'format' => 'csv']))
        ->assertOk();
});

it('forbids reports for users without a reporting permission', function () {
    $this->actingAs(User::factory()->create())->get(route('reports.index'))->assertForbidden();
});
