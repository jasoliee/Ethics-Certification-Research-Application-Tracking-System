@props(['timeline'])

@if ($timeline->isNotEmpty())
    <div class="dashboard-timeline">
        <ol>
            @foreach ($timeline as $milestone)
                <li class="{{ $milestone['is_complete'] ? 'is-complete' : '' }} {{ $milestone['is_current'] ? 'is-current' : '' }}">
                    <span class="dashboard-timeline-marker">
                        @if ($milestone['is_complete'])<x-dashboard.icon name="check" size="13" />@endif
                    </span>
                    <span class="dashboard-timeline-label">{{ $milestone['label'] }}</span>
                    <time>{{ $milestone['date_label'] }}</time>
                </li>
            @endforeach
        </ol>
    </div>
@else
    <x-dashboard.empty-state
        image="no-timeline"
        alt="Milestone flag with no available timeline"
        title="Timeline not available"
        message="Review milestones will appear when an active cycle is configured."
        compact
    />
@endif
