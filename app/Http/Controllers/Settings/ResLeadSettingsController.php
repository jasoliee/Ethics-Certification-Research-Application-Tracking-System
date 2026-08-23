<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ChangeProfileOptionStatusRequest;
use App\Http\Requests\Identity\StoreProfileOptionRequest;
use App\Http\Requests\Identity\UpdateProfileOptionRequest;
use App\Http\Requests\Settings\UpdateDeadlineSettingsRequest;
use App\Http\Requests\Settings\UpdateOwnEmailRequest;
use App\Http\Requests\Settings\UpdateOwnPasswordRequest;
use App\Http\Requests\Settings\UpdateOwnProfileRequest;
use App\Http\Requests\Settings\UpdateOwnUsernameRequest;
use App\Http\Requests\Settings\UpdateSignatoryRequest;
use App\Http\Requests\Settings\UploadManagedBackgroundRequest;
use App\Http\Requests\Settings\SaveDocumentRequirementRequest;
use App\Models\CertificateBackground;
use App\Models\DocumentRequirement;
use App\Models\ProfileOption;
use App\Services\Certificates\CertificateBackgroundService;
use App\Services\Certificates\DefaultCertificateQrService;
use App\Services\Identity\ProfileOptionCatalog;
use App\Services\Settings\AcademicTermResolver;
use App\Services\Settings\DeadlineConfigurationService;
use App\Services\Settings\DocumentRequirementConfigurationService;
use App\Services\Settings\SelfAccountSettingsService;
use App\Services\Settings\SignatorySettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Presents and updates the RES Lead-only configuration workspace.
 */
class ResLeadSettingsController extends Controller
{
    public function index(
        Request $request,
        DeadlineConfigurationService $deadlines,
        AcademicTermResolver $terms,
        ProfileOptionCatalog $profileOptions,
        CertificateBackgroundService $backgrounds,
        DocumentRequirementConfigurationService $requirementConfiguration,
    ): View {
        $configuredTerm = $terms->latestConfigured();
        $currentTerm = $terms->current();
        $settings = $deadlines->settings($configuredTerm);
        $upcomingDeadline = collect($settings)
            ->filter(fn (array $process): bool => $process['configuration']?->due_at?->isFuture() === true)
            ->sortBy(fn (array $process): int => $process['configuration']->due_at->timestamp)
            ->first();
        $settingsUser = $request->user();
        $optionRecords = ProfileOption::query()
            ->with('aliases:id,profile_option_id,value')
            ->orderBy('field')
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get();
        $backgrounds->active(CertificateBackground::TYPE_CERTIFICATE);
        $backgrounds->active(CertificateBackground::TYPE_REVIEW_WORKSHEET);
        $managedBackgrounds = CertificateBackground::query()
            ->latest('asset_version')
            ->get()
            ->groupBy('background_type');
        $documentRequirements = DocumentRequirement::query()
            ->where('is_active', true)
            ->withCount('applicationDocuments')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('settings.res-lead', [
            'pageTitle' => 'Settings',
            'settings' => $settings,
            'configuredTerm' => $configuredTerm,
            'activeTermLabel' => $currentTerm?->label() ?? AcademicTermResolver::FALLBACK_LABEL,
            'upcomingDeadline' => $upcomingDeadline,
            'minimumDate' => now()->toDateString(),
            'minimumDeadline' => now()->addMinute()->startOfMinute()->format('Y-m-d\TH:i'),
            'settingsUser' => $settingsUser,
            'settingsRouteBase' => 'res.settings',
            'profileOptions' => $profileOptions->groupedForUser($settingsUser),
            'profileOptionRecords' => $optionRecords,
            'profileOptionUsageCounts' => $profileOptions->usageCounts($optionRecords),
            'profileOptionCounts' => [
                'active' => $optionRecords->where('is_active', true)->count(),
                'inactive' => $optionRecords->where('is_active', false)->count(),
            ],
            'managedBackgrounds' => $managedBackgrounds,
            'documentRequirements' => $documentRequirements,
            'requirementsLockedTerm' => $requirementConfiguration->structuralChangesLocked(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Settings'],
            ],
        ]);
    }

    public function storeDocumentRequirement(
        SaveDocumentRequirementRequest $request,
        DocumentRequirementConfigurationService $requirements,
    ): RedirectResponse {
        $requirements->create($request->user(), $request->validated());

        return redirect()->route('res.settings.index', ['tab' => 'requirements'])
            ->with('status', 'The document requirement was added and is now available across ECRATS.');
    }

    public function updateDocumentRequirement(
        SaveDocumentRequirementRequest $request,
        DocumentRequirement $documentRequirement,
        DocumentRequirementConfigurationService $requirements,
    ): RedirectResponse {
        $requirements->update($request->user(), $documentRequirement, $request->validated());

        return redirect()->route('res.settings.index', ['tab' => 'requirements'])
            ->with('status', 'The requirement specification was updated across ECRATS.');
    }

    public function destroyDocumentRequirement(
        Request $request,
        DocumentRequirement $documentRequirement,
        DocumentRequirementConfigurationService $requirements,
    ): RedirectResponse {
        $requirements->deactivate($request->user(), $documentRequirement);

        return redirect()->route('res.settings.index', ['tab' => 'requirements'])
            ->with('status', 'The requirement was removed from the active catalogue. Historical records were preserved.');
    }

    public function updateDeadlines(
        UpdateDeadlineSettingsRequest $request,
        DeadlineConfigurationService $deadlines,
    ): RedirectResponse {
        $deadlines->update($request->user(), $request->validated());

        return back()->with('status', 'Semester, academic year, deadlines, and process availability were updated.');
    }

    public function updateUsername(
        UpdateOwnUsernameRequest $request,
        SelfAccountSettingsService $settings,
    ): RedirectResponse {
        $settings->updateUsername($request->user(), $request->validated('username'));

        return back()->with('status', 'Your username was updated.');
    }

    public function updateProfile(
        UpdateOwnProfileRequest $request,
        SelfAccountSettingsService $settings,
    ): RedirectResponse {
        $settings->updateProfile($request->user(), $request->validated());

        return back()->with('status', 'Your profile information was updated.');
    }

    public function updateEmail(
        UpdateOwnEmailRequest $request,
        SelfAccountSettingsService $settings,
    ): RedirectResponse {
        $settings->updateEmail($request->user(), $request->validated('email'), $request->session()->getId());
        $request->session()->regenerate();

        return back()->with('status', 'Your email address was updated and other signed-in sessions were revoked.');
    }

    public function updatePassword(
        UpdateOwnPasswordRequest $request,
        SelfAccountSettingsService $settings,
    ): RedirectResponse|JsonResponse {
        $settings->updatePassword($request->user(), $request->validated('password'), $request->session()->getId());
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your password was changed securely.',
            ]);
        }

        return back()->with('status', 'Your password was changed and other signed-in sessions were revoked.');
    }

    public function updateSignatory(
        UpdateSignatoryRequest $request,
        SignatorySettingsService $signatory,
    ): RedirectResponse {
        $signatory->update(
            $request->user(),
            $request->validated('certificate_signatory_name'),
            $request->file('signature'),
            $request->validated('certificate_valid_until'),
            $request->file('qr_image'),
        );

        return back()->with('status', 'The certificate signatory was updated for future certificates.');
    }

    public function previewSignatory(Request $request): BinaryFileResponse|StreamedResponse
    {
        abort_unless($request->user()->can('manageCertificateSignatory', $request->user()), 403);
        $user = $request->user();
        $disk = Storage::disk('local');

        if (filled($user->certificate_signature_path)) {
            abort_unless($disk->exists($user->certificate_signature_path), 404);
            $actualHash = hash_file('sha256', $disk->path($user->certificate_signature_path));
            abort_unless(is_string($actualHash)
                && hash_equals((string) $user->certificate_signature_sha256, $actualHash), 404);

            return $disk->response(
                $user->certificate_signature_path,
                'res-signatory-signature.png',
                $this->privateHeaders('image/png'),
                'inline',
            );
        }

        return response()->file(
            base_path(SignatorySettingsService::OFFICIAL_SIGNATURE_RESOURCE),
            $this->privateHeaders('image/png'),
        );
    }

    public function previewCertificateQr(Request $request, DefaultCertificateQrService $defaultQr): StreamedResponse
    {
        abort_unless($request->user()->can('manageCertificateSignatory', $request->user()), 403);
        $user = $request->user();
        $disk = Storage::disk('local');
        $configuredPath = filled($user->certificate_qr_path) ? $user->certificate_qr_path : null;
        $asset = $configuredPath === null ? $defaultQr->asset() : null;
        $path = $configuredPath ?? $asset['stored_path'];
        $expectedHash = $configuredPath === null ? $asset['sha256'] : $user->certificate_qr_sha256;
        abort_unless($disk->exists($path), 404);
        $actualHash = hash_file('sha256', $disk->path($path));
        abort_unless(is_string($actualHash)
            && hash_equals((string) $expectedHash, $actualHash), 404);

        return $disk->response(
            $path,
            'certificate-qr.png',
            $this->privateHeaders('image/png'),
            'inline',
        );
    }

    public function storeProfileOption(
        StoreProfileOptionRequest $request,
        ProfileOptionCatalog $profileOptions,
    ): RedirectResponse {
        $field = ProfileOptionField::from($request->validated('option_field'));
        $profileOptions->create($request->user(), $field, $request->validated('option_value'));

        return redirect()->route('res.settings.index', ['tab' => 'options'])
            ->with('status', "{$field->label()} option added.");
    }

    public function updateProfileOption(
        UpdateProfileOptionRequest $request,
        ProfileOption $profileOption,
        ProfileOptionCatalog $profileOptions,
    ): RedirectResponse {
        $profileOptions->update($request->user(), $profileOption, $request->validated('option_value'));

        return redirect()->route('res.settings.index', ['tab' => 'options'])
            ->with('status', 'Dropdown option updated. Historical values remain readable.');
    }

    public function changeProfileOptionStatus(
        ChangeProfileOptionStatusRequest $request,
        ProfileOption $profileOption,
        ProfileOptionCatalog $profileOptions,
    ): RedirectResponse {
        $active = $request->boolean('is_active');
        $profileOptions->setActive($request->user(), $profileOption, $active);

        return redirect()->route('res.settings.index', ['tab' => 'options'])
            ->with('status', $active ? 'Dropdown option restored.' : 'Dropdown option deactivated.');
    }

    public function uploadBackground(
        UploadManagedBackgroundRequest $request,
        CertificateBackgroundService $backgrounds,
    ): RedirectResponse {
        $backgrounds->uploadAndActivate(
            $request->user(),
            $request->file('background'),
            $request->validated('background_type'),
        );

        return redirect()->route('res.settings.index', ['tab' => 'backgrounds'])
            ->with('status', 'The validated background is active for future output. Issued files were not changed.');
    }

    public function activateBackground(
        Request $request,
        CertificateBackground $certificateBackground,
        CertificateBackgroundService $backgrounds,
    ): RedirectResponse {
        $backgrounds->activate($request->user(), $certificateBackground);

        return redirect()->route('res.settings.index', ['tab' => 'backgrounds'])
            ->with('status', 'The selected background is active for future output. Issued files were not changed.');
    }

    public function resetBackground(
        Request $request,
        CertificateBackgroundService $backgrounds,
    ): RedirectResponse {
        $validated = $request->validate([
            'background_type' => ['required', 'in:certificate,review_worksheet'],
        ]);
        $backgrounds->resetToOfficial($request->user(), $validated['background_type']);

        return redirect()->route('res.settings.index', ['tab' => 'backgrounds'])
            ->with('status', 'The official default is active for future output. Issued files were not changed.');
    }

    public function previewBackground(
        Request $request,
        CertificateBackground $certificateBackground,
        CertificateBackgroundService $backgrounds,
    ): StreamedResponse {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $disk = Storage::disk('local');
        abort_unless($backgrounds->isIntact($certificateBackground), 404);

        return $disk->response(
            $certificateBackground->stored_file_path,
            $certificateBackground->original_file_name,
            $this->privateHeaders($certificateBackground->mime_type),
            'inline',
        );
    }

    /** @return array<string, string> */
    private function privateHeaders(string $mimeType): array
    {
        return [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'; sandbox",
        ];
    }
}
