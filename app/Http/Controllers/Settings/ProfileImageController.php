<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileImageRequest;
use App\Services\AuditLogService;
use App\Services\Settings\ProfileImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileImageController extends Controller
{
    public function show(Request $request, ProfileImageService $images): BinaryFileResponse
    {
        $path = $images->path($request->user());
        abort_unless($path, 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(
        UpdateProfileImageRequest $request,
        ProfileImageService $images,
        AuditLogService $auditLog,
    ): RedirectResponse {
        $images->store($request->user(), $request->file('profile_image'));
        $auditLog->record($request->user(), 'user.profile_image_updated', $request->user(), ['result' => 'updated']);

        return back()->with('status', 'Profile image updated.');
    }

    public function destroy(
        Request $request,
        ProfileImageService $images,
        AuditLogService $auditLog,
    ): RedirectResponse {
        $images->delete($request->user());
        $auditLog->record($request->user(), 'user.profile_image_removed', $request->user(), ['result' => 'initials_restored']);

        return back()->with('status', 'Profile image removed. Your initials are shown by default.');
    }
}
