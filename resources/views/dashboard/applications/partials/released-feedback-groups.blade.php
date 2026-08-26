@if ($reviewerGroups->isEmpty())
    <p class="revision-empty-state">{{ $emptyMessage ?? 'No detailed comments were released for this version.' }}</p>
@else
    <div class="revision-reviewer-feedback-grid">
        @foreach ($reviewerGroups as $reviewerGroup)
            <section class="revision-anonymous-reviewer-group">
                <h4>{{ $reviewerGroup['label'] }} <small>{{ $reviewerGroup['release_label'] }}</small></h4>
                @foreach ($reviewerGroup['comments'] as $comment)
                    <article>
                        <header>
                            <x-dashboard.status-badge :label="$comment->category->label()" :tone="$comment->category->tone()" />
                            <span>{{ $comment->is_decision_comment ? 'Decision comment' : $comment->scope->label() }}</span>
                            <time datetime="{{ $comment->released_at?->toIso8601String() }}">Released {{ $comment->released_at?->format('M j, Y') }}</time>
                        </header>
                        <p>{{ $comment->body }}</p>
                        @if ($comment->document_version || $comment->page_number)
                            <small>
                                @if ($comment->document_version)Document version {{ $comment->document_version }}@endif
                                @if ($comment->document_version && $comment->page_number) · @endif
                                @if ($comment->page_number)Page {{ $comment->page_number }}@endif
                            </small>
                        @endif
                    </article>
                @endforeach
            </section>
        @endforeach
    </div>
@endif
