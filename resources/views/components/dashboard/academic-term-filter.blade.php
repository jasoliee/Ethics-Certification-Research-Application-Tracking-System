@props([
    'termOptions',
    'filters' => [],
    'label' => 'Academic Term',
])

<form class="dashboard-term-filter" method="GET" action="{{ request()->url() }}">
    <label>
        <span>{{ $label }}</span>
        <select name="academic_term_id">
            <option value="">All terms</option>
            @foreach ($termOptions as $term)
                <option value="{{ $term->id }}" @selected((string) ($filters['academic_term_id'] ?? '') === (string) $term->id)>
                    {{ $term->label() }}
                </option>
            @endforeach
        </select>
    </label>
    <button class="dashboard-primary-action" type="submit">Apply</button>
    @if (filled($filters['academic_term_id'] ?? null))
        <a class="dashboard-outline-action" href="{{ request()->url() }}">Clear</a>
    @endif
</form>
