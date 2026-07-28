<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\ResearchApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class AdviserApplicationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_adviser_dashboard_list_and_detail_show_only_formally_submitted_assigned_applications(): void
    {
        // Arrange assigned, draft, other-Adviser, and unsubmitted legacy records with distinct titles.
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $otherAdviser = User::factory()->create(['role' => UserRole::Adviser]);
        $assigned = ResearchApplication::factory()->submittedToAdviser($adviser)->create([
            'research_title' => 'Assigned Formal Submission',
        ]);
        ResearchApplication::factory()->create([
            'adviser_user_id' => $adviser,
            'research_title' => 'Private Assigned Draft',
        ]);
        ResearchApplication::factory()->submittedToAdviser($otherAdviser)->create([
            'research_title' => 'Another Adviser Submission',
        ]);
        ResearchApplication::factory()->create([
            'adviser_user_id' => $adviser,
            'application_status' => ApplicationStatus::SubmittedToAdviser,
            'research_title' => 'Missing Formal Timestamp',
            'submitted_at' => null,
        ]);
        $archived = ResearchApplication::factory()->submittedToAdviser($adviser)->create([
            'application_status' => ApplicationStatus::Archived,
            'research_title' => 'Archived Assigned Submission',
        ]);

        // Act on the Adviser dashboard and assert only the formal assigned row contributes to data and counts.
        $this->actingAs($adviser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Assigned Formal Submission')
            ->assertSee('Pending: 1', false)
            ->assertDontSee('Private Assigned Draft')
            ->assertDontSee('Another Adviser Submission')
            ->assertDontSee('Missing Formal Timestamp')
            ->assertDontSee('Archived Assigned Submission');

        // Act on the full list and assert the same scope is retained.
        $listResponse = $this->actingAs($adviser)->get(route('adviser.applications.index'));
        $listResponse->assertOk()
            ->assertSee('Assigned Formal Submission')
            ->assertSee('dashboard-overflow-region', false)
            ->assertSee('dashboard-table-status', false)
            ->assertSee('dashboard-table-action', false)
            ->assertDontSee('Private Assigned Draft')
            ->assertDontSee('Another Adviser Submission')
            ->assertDontSee('Missing Formal Timestamp')
            ->assertDontSee('Archived Assigned Submission')
            ->assertViewHas('applications', fn (LengthAwarePaginator $applications): bool => $applications->total() === 1);

        // Act on detail routes and assert assignment ownership controls record visibility.
        $this->actingAs($adviser)
            ->get(route('adviser.applications.show', $assigned))
            ->assertOk()
            ->assertSee('Assigned Formal Submission');
        $this->actingAs($otherAdviser)
            ->get(route('adviser.applications.show', $assigned))
            ->assertForbidden();
        $this->actingAs($adviser)
            ->get(route('adviser.applications.show', $archived))
            ->assertForbidden();
    }

    public function test_adviser_application_list_is_paginated_searchable_and_scoped_before_filters(): void
    {
        // Arrange sixteen assigned submissions, one searchable identity, and an out-of-scope matching record.
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $otherAdviser = User::factory()->create(['role' => UserRole::Adviser]);
        ResearchApplication::factory()
            ->count(15)
            ->submittedToAdviser($adviser)
            ->create();
        $searchApplicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'name' => 'Searchable Applicant',
            'institutional_identifier' => 'KLD-SEARCH-001',
            'program' => 'Bachelor of Science in Computer Science',
        ]);
        $searchable = ResearchApplication::factory()->submittedToAdviser($adviser)->create([
            'applicant_user_id' => $searchApplicant,
            'research_title' => 'Distinct Ethics Search Record',
            'program' => 'Bachelor of Science in Computer Science',
        ]);
        ResearchApplication::factory()->submittedToAdviser($otherAdviser)->create([
            'research_title' => 'Distinct Ethics Search Record Outside Scope',
        ]);

        // Act without filters and assert stable fifteen-row pagination over sixteen scoped records.
        $this->actingAs($adviser)
            ->get(route('adviser.applications.index'))
            ->assertOk()
            ->assertViewHas('applications', function (LengthAwarePaginator $applications): bool {
                return $applications->total() === 16
                    && $applications->perPage() === 15
                    && $applications->count() === 15;
            });

        // Act with an applicant-identifier filter and assert other Advisers remain excluded.
        $this->actingAs($adviser)
            ->get(route('adviser.applications.index', ['q' => 'KLD-SEARCH-001']))
            ->assertOk()
            ->assertSee($searchable->application_code)
            ->assertSee('Searchable Applicant')
            ->assertDontSee('Outside Scope')
            ->assertViewHas('applications', fn (LengthAwarePaginator $applications): bool => $applications->total() === 1);
    }
}
