<?php

namespace Database\Seeders;

use App\Enums\EventType;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\CalendarEvent;
use App\Models\Presentation;
use App\Models\Task;
use App\Models\User;
use App\Services\Reminders\ReminderPlanner;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Sample accounts and records for local development only.
 *
 * Run with: php artisan db:seed --class=DevelopmentSeeder
 * Every sample account uses the password "password". Never run this in production.
 */
class DevelopmentSeeder extends Seeder
{
    /**
     * Seed sample data.
     */
    public function run(ReminderPlanner $planner): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DevelopmentSeeder only runs in the local or testing environment.');
        }

        $this->call(RolesAndPermissionsSeeder::class);

        $timezone = config('app.default_user_timezone', 'UTC');

        $admin = User::factory()->administrator()->create(['name' => 'Avery Admin', 'email' => 'admin@example.test', 'timezone' => $timezone]);
        $assistant = User::factory()->assistant()->create(['name' => 'Sam Assistant', 'email' => 'assistant@example.test', 'timezone' => $timezone]);
        $executive = User::factory()->executive()->create(['name' => 'Jordan Executive', 'email' => 'executive@example.test', 'timezone' => $timezone]);

        $day = now($timezone)->startOfDay();
        $meetings = [
            ['Project Update', EventType::InternalMeeting, 0, '09:00', 30, 'Conference Room A'],
            ['Team Strategy Review', EventType::InternalMeeting, 0, '11:00', 60, 'Board Room'],
            ['Client Presentation', EventType::Presentation, 0, '14:00', 60, 'Client HQ'],
            ['Executive Sync', EventType::InternalMeeting, 1, '16:00', 30, null],
            ['Candidate Interview', EventType::Interview, 2, '10:00', 45, 'Meeting Room 3'],
            ['Partner Lunch', EventType::ExternalMeeting, 3, '12:30', 90, 'Harbor Restaurant'],
            ['Flight to Singapore', EventType::Travel, 5, '07:00', 240, 'Airport Terminal 3'],
        ];

        foreach ($meetings as [$title, $type, $offset, $time, $minutes, $location]) {
            $start = $day->addDays($offset)->setTimeFromTimeString($time)->utc();

            $event = CalendarEvent::factory()->for($executive, 'owner')->create([
                'created_by' => $assistant->id,
                'title' => $title,
                'type' => $type,
                'priority' => $type === EventType::Presentation ? Priority::High : Priority::Normal,
                'starts_at' => $start,
                'ends_at' => $start->addMinutes($minutes),
                'timezone' => $timezone,
                'location' => $location,
                'meeting_url' => $type === EventType::InternalMeeting ? 'https://meet.example.test/'.str()->slug($title) : null,
                'agenda' => "Agenda for {$title}.",
            ]);

            $planner->syncEvent($event);
        }

        $tasks = [
            ['Review budget proposal', Priority::Urgent, TaskStatus::Pending, 0],
            ['Prepare board presentation', Priority::High, TaskStatus::InProgress, 2],
            ['Follow up with client', Priority::Normal, TaskStatus::Pending, 3],
            ['Approve travel plan', Priority::Normal, TaskStatus::Completed, -1],
            ['Sign vendor contract', Priority::High, TaskStatus::Pending, -2],
        ];

        foreach ($tasks as [$title, $priority, $status, $offset]) {
            $task = Task::factory()->for($executive, 'assignee')->create([
                'created_by' => $assistant->id,
                'title' => $title,
                'priority' => $priority,
                'status' => $status,
                'due_at' => $day->addDays($offset)->setTime(17, 0)->utc(),
                'timezone' => $timezone,
                'completed_at' => $status === TaskStatus::Completed ? now() : null,
            ]);

            $planner->syncTask($task);
        }

        $presentation = Presentation::factory()->for($executive, 'owner')->create([
            'created_by' => $assistant->id,
            'title' => 'Quarterly Results',
            'timezone' => $timezone,
            'presentation_at' => $day->addDays(7)->setTime(10, 0)->utc(),
            'preparation_deadline_at' => $day->addDays(4)->setTime(17, 0)->utc(),
            'review_deadline_at' => $day->addDays(6)->setTime(12, 0)->utc(),
        ]);
        $planner->syncPresentation($presentation);

        $this->command?->info("Sample accounts created ({$admin->email}, {$assistant->email}, {$executive->email}); password: \"password\". Local development only.");
    }
}
