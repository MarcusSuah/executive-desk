<?php

use App\Models\CalendarEvent;
use App\Models\Presentation;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->executive = User::factory()->executive()->create();
    $this->event = CalendarEvent::factory()->for($this->executive, 'owner')->create();
    $this->task = Task::factory()->for($this->executive, 'assignee')->create();
    $this->presentation = Presentation::factory()->for($this->executive, 'owner')->create();
});

it('renders every workspace page for an assistant', function (string $route, string $component, ?string $parameter) {
    $assistant = User::factory()->assistant()->create();

    $this->actingAs($assistant)
        ->get(route($route, $parameter ? $this->{$parameter} : []))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['dashboard', 'dashboard', null],
    ['calendar', 'calendar/index', null],
    ['events.create', 'events/form', null],
    ['events.show', 'events/show', 'event'],
    ['events.edit', 'events/form', 'event'],
    ['tasks.index', 'tasks/index', null],
    ['tasks.create', 'tasks/form', null],
    ['tasks.show', 'tasks/show', 'task'],
    ['tasks.edit', 'tasks/form', 'task'],
    ['presentations.index', 'presentations/index', null],
    ['presentations.create', 'presentations/form', null],
    ['presentations.show', 'presentations/show', 'presentation'],
    ['presentations.edit', 'presentations/form', 'presentation'],
    ['briefings.index', 'briefings/index', null],
    ['reports.index', 'reports/index', null],
    ['notifications.history', 'notifications/history', null],
    ['notifications.edit', 'settings/notifications', null],
    ['profile.edit', 'settings/profile', null],
]);

it('renders the executive views of their own records', function (string $route, string $component, ?string $parameter) {
    $this->actingAs($this->executive)
        ->get(route($route, $parameter ? $this->{$parameter} : []))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['dashboard', 'dashboard', null],
    ['calendar', 'calendar/index', null],
    ['events.show', 'events/show', 'event'],
    ['tasks.show', 'tasks/show', 'task'],
    ['presentations.show', 'presentations/show', 'presentation'],
    ['reports.index', 'reports/index', null],
]);

it('renders the administration pages for an administrator', function (string $route, string $component) {
    $this->actingAs(User::factory()->administrator()->create())
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['admin.users.index', 'admin/users/index'],
    ['admin.users.create', 'admin/users/form'],
    ['admin.settings.edit', 'admin/settings'],
    ['admin.audit-logs.index', 'admin/audit-logs'],
]);

it('forbids executives from editing pages', function (string $route, string $parameter) {
    $this->actingAs($this->executive)->get(route($route, $this->{$parameter}))->assertForbidden();
})->with([
    ['events.edit', 'event'],
    ['tasks.edit', 'task'],
    ['presentations.edit', 'presentation'],
]);
