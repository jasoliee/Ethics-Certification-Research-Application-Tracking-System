<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\RoleHome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationPageController extends Controller
{
    public function index(Request $request): View
    {
        // Pagination keeps the notification page responsive as an account's history grows.
        return view('dashboard.notifications', [
            'pageTitle' => 'Notifications',
            'notifications' => $request->user()->notifications()
                ->select(['id', 'data', 'read_at', 'created_at'])
                ->latest()
                ->paginate(20),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => RoleHome::routeNameFor($request->user()->role)],
                ['label' => 'Notifications'],
            ],
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Notifications marked as read.');
    }
}
