<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

class DashboardNavigation
{
    /**
     * @return array<int, array{label: string, route?: string, icon: string, active: string, children?: array<int, array{label: string, route: string, icon: string, active: string}>}>
     */
    public static function for(UserRole|User $subject): array
    {
        $user = $subject instanceof User ? $subject : null;
        $role = $user?->role ?? $subject;

        return match ($role) {
            UserRole::Applicant => [
                self::item('Home', 'dashboard', 'home', 'dashboard'),
                self::item('Application', 'applicant.applications.index', 'file-text', 'applicant.applications.*'),
                self::item('Revision and Certificates', 'applicant.revision-certificates.index', 'award', 'applicant.revision-certificates.*'),
            ],
            UserRole::Adviser => array_values(array_filter([
                self::item('Home', 'dashboard', 'home', 'dashboard'),
                self::item('Application', 'adviser.applications.index', 'file-text', 'adviser.applications.*'),
                self::item('Applicants', 'adviser.applicants.index', 'user-check', 'adviser.applicants.*'),
                $user?->hasReviewerAccess() ? self::group('Reviewer', 'clipboard', 'reviewer.*', [
                    self::item('Reviewer Dashboard', 'reviewer.dashboard', 'home', 'reviewer.dashboard'),
                    self::item('Assignments', 'reviewer.assignments.index', 'clipboard', 'reviewer.assignments.*|reviewer.reviews.*'),
                ]) : null,
            ])),
            // The legacy value remains readable for historical rows and audit metadata only.
            UserRole::Reviewer => [],
            UserRole::ResLead => [
                self::item('Home', 'dashboard', 'home', 'dashboard'),
                self::item('Applications', 'res.applications.index', 'file-text', 'res.applications.*'),
                self::item('Review Monitoring', 'res.review-monitoring.index', 'users', 'res.review-monitoring.*'),
                self::item('Decision & Certificates', 'res.certificates.index', 'award', 'res.certificates.*'),
                self::item('Reports', 'res.reports.index', 'chart', 'res.reports.*'),
                self::item('User Management', 'res.users.index', 'user', 'res.users.*'),
            ],
        };
    }

    public static function notificationsRoute(UserRole $role): string
    {
        return match ($role) {
            UserRole::Applicant => 'applicant.notifications.index',
            UserRole::Adviser => 'adviser.notifications.index',
            UserRole::Reviewer => 'reviewer.notifications.index',
            UserRole::ResLead => 'res.notifications.index',
        };
    }

    public static function settingsRoute(UserRole $role): string
    {
        return match ($role) {
            UserRole::Applicant => 'applicant.settings.index',
            UserRole::Adviser => 'adviser.settings.index',
            UserRole::Reviewer => 'reviewer.settings.index',
            UserRole::ResLead => 'res.settings.index',
        };
    }

    public static function profileRoute(UserRole $role): string
    {
        return self::settingsRoute($role);
    }

    public static function applicationsRoute(UserRole $role): string
    {
        return match ($role) {
            UserRole::Applicant => 'applicant.applications.index',
            UserRole::Adviser => 'adviser.applications.index',
            UserRole::Reviewer => 'reviewer.assignments.index',
            UserRole::ResLead => 'res.applications.index',
        };
    }

    /** @return array{label: string, route: string, icon: string, active: string} */
    private static function item(string $label, string $route, string $icon, string $active): array
    {
        return compact('label', 'route', 'icon', 'active');
    }

    /**
     * @param  array<int, array{label: string, route: string, icon: string, active: string}>  $children
     * @return array{label: string, icon: string, active: string, children: array<int, array{label: string, route: string, icon: string, active: string}>}
     */
    private static function group(string $label, string $icon, string $active, array $children): array
    {
        return compact('label', 'icon', 'active', 'children');
    }
}
