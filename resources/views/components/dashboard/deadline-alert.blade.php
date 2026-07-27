@props(['deadline' => null, 'emptyTitle' => 'No urgent deadlines', 'emptyMessage' => 'You are currently up to date.'])

@if ($deadline)
    @php($deadlineState = $deadline['state'] ?? 'open')
    {{-- Configured submission deadlines distinguish open, upcoming, and closed server-calculated states. --}}
    <div class="dashboard-deadline-active">
        <span class="dashboard-deadline-icon"><x-dashboard.icon name="calendar" size="43" /></span>
        <div>
            <span>{{ $deadline['title'] }}</span>
            <strong>
                @if ($deadlineState === 'closed')
                    Submission closed
                @elseif ($deadlineState === 'upcoming')
                    Submission opens soon
                @else
                    {{ $deadline['days'] }} {{ Str::plural('day', $deadline['days']) }} remaining
                @endif
            </strong>
            <small>{{ $deadline['message'] ?? 'Deadline: '.$deadline['due_label'] }}</small>
        </div>
    </div>
@else
    {{-- Missing deadline configuration retains the role-specific empty state. --}}
    <x-dashboard.empty-state
        image="no-deadlines"
        alt="Calendar with no pending deadline"
        :title="$emptyTitle"
        :message="$emptyMessage"
        compact
    />
@endif
