export type Option<T extends string | number = string> = {
    value: T;
    label: string;
};

export type UserSummary = {
    id: number;
    name: string;
    email: string;
    timezone: string;
};

export type UserOption = {
    id: number;
    name: string;
    email?: string;
    timezone?: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        per_page: number;
        to: number | null;
        total: number;
    };
};

export type Collection<T> = {
    data: T[];
};

export type Priority = 'low' | 'normal' | 'high' | 'urgent';

export type ChecklistItem = {
    id: number;
    title: string;
    is_completed: boolean;
    completed_at: string | null;
    position: number;
};

export type Attachment = {
    id: number;
    original_name: string;
    mime_type: string;
    size: number;
    version: number;
    label: string | null;
    uploaded_by?: string | null;
    created_at: string | null;
    download_url: string;
    can_delete: boolean;
};

export type Attendee = {
    id: number;
    user_id: number | null;
    name: string;
    email: string | null;
    role: string;
    response_status: string;
};

export type CalendarEvent = {
    id: number;
    title: string;
    description: string | null;
    type: string;
    type_label: string;
    category: string | null;
    priority: Priority;
    status: 'scheduled' | 'completed' | 'cancelled';
    starts_at: string;
    ends_at: string;
    timezone: string;
    all_day: boolean;
    location: string | null;
    meeting_url: string | null;
    agenda: string | null;
    notes: string | null;
    preparation_notes: string | null;
    reminder_minutes: number[] | null;
    conflict_acknowledged: boolean;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    version: number;
    owner?: UserSummary;
    creator?: UserSummary | null;
    attendees?: Attendee[];
    checklist?: ChecklistItem[];
    attachments?: Attachment[];
    tasks?: Task[];
    presentations?: Presentation[];
    created_at: string | null;
    updated_at: string | null;
};

export type TaskStatus = 'pending' | 'in_progress' | 'completed' | 'cancelled';

export type Task = {
    id: number;
    title: string;
    description: string | null;
    priority: Priority;
    status: TaskStatus;
    project: string | null;
    starts_at: string | null;
    due_at: string | null;
    timezone: string;
    estimated_minutes: number | null;
    completed_at: string | null;
    notes: string | null;
    is_overdue: boolean;
    recurrence_frequency: string | null;
    recurrence_interval: number | null;
    recurrence_ends_on: string | null;
    recurrence_parent_id: number | null;
    occurrence_date: string | null;
    calendar_event_id: number | null;
    updated_at: string | null;
    assignee?: UserSummary | null;
    creator?: UserSummary | null;
    completer?: UserSummary | null;
    event?: { id: number; title: string; starts_at: string } | null;
    checklist?: ChecklistItem[];
    checklist_total?: number;
    checklist_completed?: number;
    attachments?: Attachment[];
};

export type PresentationStatus =
    | 'draft'
    | 'in_preparation'
    | 'under_review'
    | 'approved'
    | 'delivered'
    | 'cancelled';

export type Presentation = {
    id: number;
    title: string;
    description: string | null;
    presentation_at: string | null;
    timezone: string;
    venue: string | null;
    meeting_url: string | null;
    preparation_deadline_at: string | null;
    review_deadline_at: string | null;
    status: PresentationStatus;
    approved_at: string | null;
    notes: string | null;
    presenter_name: string | null;
    calendar_event_id: number | null;
    owner?: UserSummary;
    presenter?: UserSummary | null;
    approver?: UserSummary | null;
    event?: { id: number; title: string; starts_at: string } | null;
    checklist?: ChecklistItem[];
    attachments?: Attachment[];
    attachments_count?: number;
};

export type Reminder = {
    id: number;
    kind: string;
    channel: string;
    channel_label: string;
    minutes_before: number;
    scheduled_for: string;
    status: string;
    status_reason: string | null;
    attempts: number;
    sent_at: string | null;
    last_error: string | null;
    recipient?: string;
    can_toggle: boolean;
};

export type AuditEntry = {
    id: number;
    action: string;
    module: string;
    result: 'success' | 'warning' | 'failed';
    actor?: string | null;
    auditable_type: string | null;
    auditable_id: number | null;
    subject?: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address?: string | null;
    created_at: string;
};

export type NotificationDelivery = {
    id: number;
    notification_type: string;
    recipient?: string | null;
    channel: string;
    channel_label: string;
    status: 'sent' | 'failed' | 'unavailable' | 'skipped';
    attempt_number: number;
    scheduled_for: string | null;
    attempted_at: string;
    provider_message_id: string | null;
    error_message: string | null;
    reminder_id: number | null;
    reminder_status: string | null;
    can_retry: boolean;
};

export type BriefingItem = {
    id: number;
    title: string;
    starts_at?: string | null;
    ends_at?: string | null;
    due_at?: string | null;
    deadline?: string | null;
    location?: string | null;
    meeting_url?: string | null;
    agenda?: string | null;
    priority?: string;
    status?: string;
    detail?: string | null;
    url?: string;
};

export type BriefingSection = {
    key: string;
    title: string;
    empty: string;
    items: BriefingItem[];
};

export type Briefing = {
    id: number;
    type: 'daily' | 'weekly';
    period_start: string;
    period_end: string;
    timezone: string;
    status: string;
    generated_at: string;
    delivered_at: string | null;
    user?: UserSummary;
    content: {
        headline?: string;
        summary?: Record<string, number>;
        sections?: BriefingSection[];
    };
};
