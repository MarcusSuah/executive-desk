<?php

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['executive-desk.audit.page_views' => false]);
    $this->travelTo(CarbonImmutable::parse('2026-10-26 15:00:00', 'UTC'));
    $this->admin = User::factory()->administrator()->create(['name' => 'Avery Admin', 'timezone' => 'UTC']);
    $this->assistant = User::factory()->assistant()->create(['name' => 'Sam Assistant']);
    AuditLog::query()->delete();

    AuditLog::factory()->for($this->assistant)->create(['action' => 'task.created']);
    AuditLog::factory()->for($this->assistant)->create(['action' => 'task.completed']);
    AuditLog::factory()->for($this->assistant)->create(['action' => 'event.created']);
    AuditLog::factory()->create(['user_id' => null, 'action' => 'auth.failed']);
    AuditLog::factory()->for($this->assistant)->create(['action' => 'request.tasks.update', 'new_values' => ['outcome' => 'rejected by validation']]);
    AuditLog::factory()->for($this->assistant)->create(['action' => 'task.deleted', 'created_at' => '2026-10-20 10:00:00']);
});

it('shows today’s totals, failures, and busiest module', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.audit-logs.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/audit-logs')
            ->where('stats.total_today', 5)
            ->where('stats.failed_today', 1)
            ->where('stats.most_active_module', 'Tasks')
            ->where('stats.most_active_count', 2)
            ->where('entries.meta.total', 6)
            ->where('entries.meta.per_page', 10));
});

it('grades each entry and names its module', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.audit-logs.index', ['per_page' => 25]))
        ->assertInertia(fn ($page) => $page
            ->where('entries.data.1.action', 'request.tasks.update')
            ->where('entries.data.1.module', 'Requests')
            ->where('entries.data.1.result', 'warning')
            ->where('entries.data.2.module', 'Sign-in')
            ->where('entries.data.2.result', 'failed')
            ->where('entries.data.3.module', 'Calendar')
            ->where('entries.data.3.result', 'success'));
});

it('filters by search, module, result, and date', function (array $query, int $expected) {
    $this->actingAs($this->admin)
        ->get(route('admin.audit-logs.index', $query))
        ->assertInertia(fn ($page) => $page->where('entries.meta.total', $expected));
})->with([
    'search by user name' => [['q' => 'Sam Assist'], 5],
    'search by action' => [['q' => 'auth.failed'], 1],
    'module' => [['module' => 'Tasks'], 3],
    'failed only' => [['result' => 'failed'], 1],
    'warnings only' => [['result' => 'warning'], 1],
    'date range' => [['from' => '2026-10-19', 'to' => '2026-10-21'], 1],
]);

it('rejects an unknown module or page size', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.audit-logs.index', ['module' => 'Nonsense', 'per_page' => 7]))
        ->assertSessionHasErrors(['module', 'per_page']);
});

it('exports the filtered entries and records the export', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.audit-logs.export', ['format' => 'csv', 'module' => 'Tasks']))
        ->assertOk()
        ->assertDownload();

    $csv = file_get_contents($response->baseResponse->getFile()->getPathname());

    expect($csv)->toContain('Date & time')
        ->toContain('Sam Assistant')
        ->toContain('Task completed')
        ->not->toContain('Auth failed');

    $this->assertDatabaseHas('audit_logs', ['action' => 'export.audit_logs', 'user_id' => $this->admin->id]);
});

it('only lets people who can view the audit trail see or export it', function () {
    $this->actingAs($this->assistant)->get(route('admin.audit-logs.index'))->assertForbidden();
    $this->actingAs($this->assistant)->get(route('admin.audit-logs.export', ['format' => 'csv']))->assertForbidden();
});
