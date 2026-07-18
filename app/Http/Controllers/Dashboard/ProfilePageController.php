<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfilePageController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('dashboard.profile', [
            'pageTitle' => 'Profile',
            'profileUser' => $request->user(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Profile'],
            ],
        ]);
    }
}
