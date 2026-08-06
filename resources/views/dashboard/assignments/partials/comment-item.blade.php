<article
    @class(['reviewer-comment-item', 'is-resolved' => $comment->status === 'resolved'])
    data-reviewer-comment-item
    data-comment-id="{{ $comment->id }}"
    data-comment-scope="{{ $comment->scope->value }}"
    data-comment-category="{{ $comment->category->value }}"
    data-comment-document-id="{{ $comment->application_document_id }}"
    data-comment-page-number="{{ $comment->page_number }}"
    data-comment-body="{{ $comment->body }}"
    data-comment-update-url="{{ route('reviewer.assignments.comments.update', [$assignment, $comment]) }}"
>
    <div class="reviewer-comment-heading">
        <div class="reviewer-comment-reference">
            <x-dashboard.status-badge :label="$comment->category->label()" :tone="$comment->category->tone()" />
            <span>
                {{ $comment->scope->label() }}
                @if ($comment->document) - {{ $comment->document->original_file_name }}@endif
                @if ($comment->page_number) - Page {{ $comment->page_number }}@endif
            </span>
            <x-dashboard.status-badge :label="$comment->status === 'resolved' ? 'Resolved' : 'Open'" :tone="$comment->status === 'resolved' ? 'success' : 'blue'" />
        </div>

        @if ($canWrite)
            <div class="reviewer-comment-actions" aria-label="Comment actions">
                <button class="reviewer-comment-action" type="button" data-reviewer-comment-edit>
                    <x-dashboard.icon name="edit" size="15" /><span>Edit</span>
                </button>
                <form method="POST" action="{{ route('reviewer.assignments.comments.status', [$assignment, $comment]) }}" data-reviewer-comment-action-form="status">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $comment->status === 'resolved' ? 'open' : 'resolved' }}">
                    <button class="reviewer-comment-action" type="submit">
                        <x-dashboard.icon :name="$comment->status === 'resolved' ? 'refresh' : 'check'" size="15" />
                        <span>{{ $comment->status === 'resolved' ? 'Reopen' : 'Resolve' }}</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('reviewer.assignments.comments.destroy', [$assignment, $comment]) }}" data-reviewer-comment-action-form="delete">
                    @csrf
                    @method('DELETE')
                    <button class="reviewer-comment-remove" type="submit" title="Remove comment" aria-label="Remove comment">
                        <x-dashboard.icon name="x" size="17" />
                    </button>
                </form>
            </div>
        @endif
    </div>
    <p>{{ $comment->body }}</p>
    <small>
        {{ $comment->created_at->format('M j, Y g:i A') }}
        @if ($comment->updated_at->greaterThan($comment->created_at)) - Updated {{ $comment->updated_at->format('M j, Y g:i A') }}@endif
    </small>
</article>
