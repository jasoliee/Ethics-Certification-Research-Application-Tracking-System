@props(['deadline' => null, 'emptyTitle' => 'No urgent deadlines', 'emptyMessage' => 'You are currently up to date.'])

@if ($deadline)
    <div class="dashboard-deadline-active">
        <span class="dashboard-deadline-icon"><x-dashboard.icon name="calendar" size="43" /></span>
        <div>
            <span>{{ $deadline['title'] }}</span>
            <strong>{{ $deadline['days'] }} {{ Str::plural('day', $deadline['days']) }}</strong>
            <small>Deadline: {{ $deadline['due_label'] }}</small>
        </div>
    </div>
@else
    <x-dashboard.empty-state
        image="no-deadlines"
        alt="Calendar with no pending deadline"
        :title="$emptyTitle"
        :message="$emptyMessage"
        compact
    />
@endif
