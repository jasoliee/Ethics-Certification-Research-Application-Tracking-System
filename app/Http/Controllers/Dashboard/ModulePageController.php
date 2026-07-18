<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\RoleHome;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModulePageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $title = (string) ($request->route('pageTitle') ?? 'Workspace');

        return view('dashboard.module', [
            'pageTitle' => $title,
            'moduleTitle' => (string) ($request->route('moduleTitle') ?? $title),
            'moduleMessage' => (string) ($request->route('moduleMessage') ?? 'No records are available in this workspace yet.'),
            'moduleIcon' => (string) ($request->route('moduleIcon') ?? 'file-text'),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => RoleHome::routeNameFor($request->user()->role)],
                ['label' => $title],
            ],
        ]);
    }
}
