<?php

namespace App\Support;

use App\Enums\UserRole;

class DashboardNavigation
{
    /** @return array<int, array{label: string, route: string, icon: string, active: string}> */
    public static function for(UserRole $role): array
    {
        return match ($role) {
            UserRole::Applicant => [
                self::item('Home', 'dashboard', 'home', 'dashboard'),
                self::item('Application', 'applicant.applications.index', 'file-text', 'applicant.applications.*'),
                self::item('Revision and Certificates', 'applicant.revision-certificates.index', 'award', 'applicant.revision-certificates.*'),
                self::item('Reports', 'applicant.reports.index', 'chart', 'applicant.reports.*'),
            ],
            UserRole::Adviser => [
                self::item('Home', 'dashboard', 'home', 'dashboard'),
                self::item('Application', 'adviser.applications.index', 'file-text', 'adviser.applications.*'),
                self::item('Applicants', 'adviser.applicants.index', 'user-check', 'adviser.applicants.*'),
            ],
            UserRole::Reviewer => [
                self::item('Home', 'dashboard', 'home', 'dashboard'),
                self::item('Assignments', 'reviewer.assignments.index', 'clipboard', 'reviewer.assignments.*'),
                self::item('Review', 'reviewer.reviews.index', 'users', 'reviewer.reviews.*'),
            ],
            UserRole::ResLead => [
                self::item('Home', 'dashboard', 'home', 'dashboard'),
                self::item('Applications', 'res.applications.index', 'file-text', 'res.applications.*'),
                self::item('Review Monitoring', 'res.review-monitoring.index', 'users', 'res.review-monitoring.*'),
                self::item('Certificates', 'res.certificates.index', 'award', 'res.certificates.*'),
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
        return match ($role) {
            UserRole::Applicant => 'applicant.profile.show',
            UserRole::Adviser => 'adviser.profile.show',
            UserRole::Reviewer => 'reviewer.profile.show',
            UserRole::ResLead => 'res.profile.show',
        };
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
}
