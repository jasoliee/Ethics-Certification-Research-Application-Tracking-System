<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DatabaseNotification;
use App\Models\User;
use App\Services\Settings\AcademicTermResolver;
use App\Support\RoleHome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NotificationPageController extends Controller
{
    public function __construct(private readonly AcademicTermResolver $terms) {}

    public function index(Request $request): View
    {
        return $this->render($request, false);
    }

    public function bin(Request $request): View
    {
        $this->purgeExpired();

        return $this->render($request, true);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Notifications marked as read.');
    }

    public function updateReadStatus(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $this->assertOwned($request->user(), $notification);
        $action = $request->validate([
            'action' => ['required', Rule::in(['mark_read', 'mark_unread'])],
        ])['action'];

        $notification->update(['read_at' => $action === 'mark_read' ? now() : null]);

        return back()->with('status', $action === 'mark_read'
            ? 'Notification marked as read.'
            : 'Notification marked as unread.');
    }

    public function destroy(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $this->assertOwned($request->user(), $notification);
        $notification->delete();

        return back()->with('status', 'Notification moved to Bin.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notification_ids' => ['required', 'array', 'min:1', 'max:100'],
            'notification_ids.*' => ['required', 'uuid', 'distinct'],
            'action' => ['required', Rule::in(['mark_read', 'mark_unread', 'delete'])],
        ]);
        $query = $request->user()->notifications()->whereIn('id', $validated['notification_ids']);
        $count = (clone $query)->count();
        $this->ensureSelection($count);

        match ($validated['action']) {
            'mark_read' => $query->update(['read_at' => now()]),
            'mark_unread' => $query->update(['read_at' => null]),
            'delete' => $query->delete(),
        };

        return back()->with('status', "{$count} selected notification(s) updated.");
    }

    public function updateAll(Request $request): RedirectResponse
    {
        $action = $request->validate([
            'action' => ['required', Rule::in(['mark_read', 'mark_unread', 'delete'])],
        ])['action'];
        $query = $request->user()->notifications();

        match ($action) {
            'mark_read' => $query->update(['read_at' => now()]),
            'mark_unread' => $query->update(['read_at' => null]),
            'delete' => $query->delete(),
        };

        return back()->with('status', match ($action) {
            'mark_read' => 'All notifications marked as read.',
            'mark_unread' => 'All notifications marked as unread.',
            'delete' => 'All notifications moved to Bin.',
        });
    }

    public function restore(Request $request, string $notification): RedirectResponse
    {
        $owned = $this->trashedOwned($request->user())->whereKey($notification)->firstOrFail();
        $owned->restore();

        return back()->with('status', 'Notification restored.');
    }

    public function forceDestroy(Request $request, string $notification): RedirectResponse
    {
        $owned = $this->trashedOwned($request->user())->whereKey($notification)->firstOrFail();
        $owned->forceDelete();

        return back()->with('status', 'Notification permanently deleted.');
    }

    public function bulkBin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notification_ids' => ['required', 'array', 'min:1', 'max:100'],
            'notification_ids.*' => ['required', 'uuid', 'distinct'],
            'action' => ['required', Rule::in(['restore', 'force_delete'])],
        ]);
        $query = $this->trashedOwned($request->user())->whereIn('id', $validated['notification_ids']);
        $count = (clone $query)->count();
        $this->ensureSelection($count);

        $validated['action'] === 'restore' ? $query->restore() : $query->forceDelete();

        return back()->with('status', "{$count} selected notification(s) updated.");
    }

    public function updateAllBin(Request $request): RedirectResponse
    {
        $action = $request->validate([
            'action' => ['required', Rule::in(['restore', 'force_delete'])],
        ])['action'];
        $query = $this->trashedOwned($request->user());

        $action === 'restore' ? $query->restore() : $query->forceDelete();

        return back()->with('status', $action === 'restore'
            ? 'All notifications restored.'
            : 'All notifications permanently deleted.');
    }

    private function render(Request $request, bool $binMode): View
    {
        $filters = validator($request->query(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', 'string', 'max:255'],
            'read_status' => ['nullable', Rule::in(['read', 'unread'])],
            'academic_term_id' => ['nullable', 'integer', Rule::exists('academic_terms', 'id')],
        ])->validate();
        $query = $binMode
            ? $this->trashedOwned($request->user())
            : $request->user()->notifications()->getQuery();
        $this->applyFilters($query, $filters);
        $typesQuery = $binMode
            ? $this->trashedOwned($request->user())
            : $request->user()->notifications()->getQuery();
        $types = $typesQuery
            // User::notifications() is newest-first. MySQL rejects that inherited
            // created_at ordering when a DISTINCT projection selects only type.
            ->reorder()
            ->whereNotNull('type')
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->mapWithKeys(fn (string $type): array => [$type => Str::headline(class_basename($type))]);

        return view('dashboard.notifications', [
            'pageTitle' => $binMode ? 'Notification Bin' : 'Notifications',
            'notifications' => $query
                ->select(['id', 'type', 'data', 'read_at', 'created_at', 'deleted_at'])
                ->latest('created_at')
                ->paginate(20)
                ->withQueryString(),
            'filters' => $filters,
            'notificationTypes' => $types,
            'binMode' => $binMode,
            'termOptions' => $this->terms->filterOptions(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => RoleHome::routeNameFor($request->user()->role)],
                ['label' => $binMode ? 'Notification Bin' : 'Notifications'],
            ],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when(filled($filters['date'] ?? null), fn (Builder $items) => $items->whereDate('created_at', $filters['date']))
            ->when(filled($filters['type'] ?? null), fn (Builder $items) => $items->where('type', $filters['type']))
            ->when(filled($filters['academic_term_id'] ?? null), fn (Builder $items) => $items->where('data->academic_term_id', (int) $filters['academic_term_id']))
            ->when(($filters['read_status'] ?? null) === 'read', fn (Builder $items) => $items->whereNotNull('read_at'))
            ->when(($filters['read_status'] ?? null) === 'unread', fn (Builder $items) => $items->whereNull('read_at'));
    }

    private function trashedOwned(User $user): Builder
    {
        return DatabaseNotification::onlyTrashed()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id);
    }

    private function assertOwned(User $user, DatabaseNotification $notification): void
    {
        abort_unless(
            $notification->notifiable_type === User::class
            && (int) $notification->notifiable_id === $user->id,
            404,
        );
    }

    private function ensureSelection(int $count): void
    {
        if ($count === 0) {
            throw ValidationException::withMessages([
                'notification_ids' => 'Select at least one notification you own.',
            ]);
        }
    }

    private function purgeExpired(): void
    {
        DatabaseNotification::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(7))
            ->forceDelete();
    }
}
