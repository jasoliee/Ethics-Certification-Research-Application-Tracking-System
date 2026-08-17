<?php $__env->startSection('content'); ?>
    <?php
        $hasReviewerFilters = collect($filters)
            ->only(['q', 'review_type', 'assignment_status', 'deadline', 'consensus'])
            ->filter(fn ($value) => filled($value))
            ->isNotEmpty();
        $hasAdviserFilters = collect($filters)
            ->only(['adviser_q', 'adviser_department', 'adviser_workload'])
            ->filter(fn ($value) => filled($value))
            ->isNotEmpty();
    ?>

    <div class="dashboard-page review-monitoring-page">
        <header class="dashboard-page-heading dashboard-page-heading-row review-monitoring-heading">
            <div>
                <h1>Review Monitoring</h1>
                <p>Track anonymous assignment progress, deadlines, Full Board agreement, and reviewer-enabled Adviser capacity.</p>
            </div>
            <a class="dashboard-outline-action dashboard-icon-text-action" href="<?php echo e(request()->fullUrl()); ?>">
                <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'refresh','size' => '17']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'refresh','size' => '17']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
                <span>Refresh</span>
            </a>
        </header>

        <nav class="review-monitoring-section-links" aria-label="Review monitoring sections">
            <a href="#review-monitoring-assignments">
                <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'file-search','size' => '18']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'file-search','size' => '18']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
                <span>Reviewer Assignments</span>
            </a>
            <a href="#review-monitoring-advisers">
                <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'user-check','size' => '18']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'user-check','size' => '18']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
                <span>Adviser Endorsements</span>
            </a>
        </nav>

        <div class="dashboard-summary-grid dashboard-summary-grid-five" aria-label="Review operations summary">
            <?php if (isset($component)) { $__componentOriginalde4374013260732cd5542914186677e7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalde4374013260732cd5542914186677e7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.summary-card','data' => ['label' => 'Active Applications','count' => $metrics['active_applications'],'icon' => 'file-search','tone' => 'blue','href' => route('res.review-monitoring.index').'#review-monitoring-assignments']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.summary-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Active Applications','count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($metrics['active_applications']),'icon' => 'file-search','tone' => 'blue','href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('res.review-monitoring.index').'#review-monitoring-assignments')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalde4374013260732cd5542914186677e7)): ?>
<?php $attributes = $__attributesOriginalde4374013260732cd5542914186677e7; ?>
<?php unset($__attributesOriginalde4374013260732cd5542914186677e7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalde4374013260732cd5542914186677e7)): ?>
<?php $component = $__componentOriginalde4374013260732cd5542914186677e7; ?>
<?php unset($__componentOriginalde4374013260732cd5542914186677e7); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginalde4374013260732cd5542914186677e7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalde4374013260732cd5542914186677e7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.summary-card','data' => ['label' => 'Active Assignments','count' => $metrics['active_assignments'],'icon' => 'users','tone' => 'violet','href' => route('res.review-monitoring.index').'#review-monitoring-assignments']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.summary-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Active Assignments','count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($metrics['active_assignments']),'icon' => 'users','tone' => 'violet','href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('res.review-monitoring.index').'#review-monitoring-assignments')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalde4374013260732cd5542914186677e7)): ?>
<?php $attributes = $__attributesOriginalde4374013260732cd5542914186677e7; ?>
<?php unset($__attributesOriginalde4374013260732cd5542914186677e7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalde4374013260732cd5542914186677e7)): ?>
<?php $component = $__componentOriginalde4374013260732cd5542914186677e7; ?>
<?php unset($__componentOriginalde4374013260732cd5542914186677e7); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginalde4374013260732cd5542914186677e7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalde4374013260732cd5542914186677e7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.summary-card','data' => ['label' => 'Assignment Completion','count' => $metrics['completion_rate'].'%','icon' => 'check','tone' => 'green','href' => route('res.review-monitoring.index').'#review-monitoring-assignments']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.summary-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Assignment Completion','count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($metrics['completion_rate'].'%'),'icon' => 'check','tone' => 'green','href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('res.review-monitoring.index').'#review-monitoring-assignments')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalde4374013260732cd5542914186677e7)): ?>
<?php $attributes = $__attributesOriginalde4374013260732cd5542914186677e7; ?>
<?php unset($__attributesOriginalde4374013260732cd5542914186677e7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalde4374013260732cd5542914186677e7)): ?>
<?php $component = $__componentOriginalde4374013260732cd5542914186677e7; ?>
<?php unset($__componentOriginalde4374013260732cd5542914186677e7); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginalde4374013260732cd5542914186677e7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalde4374013260732cd5542914186677e7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.summary-card','data' => ['label' => 'Overdue Assignments','count' => $metrics['overdue_assignments'],'icon' => 'clock','tone' => 'orange','href' => route('res.review-monitoring.index', ['deadline' => 'overdue']).'#review-monitoring-assignments']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.summary-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Overdue Assignments','count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($metrics['overdue_assignments']),'icon' => 'clock','tone' => 'orange','href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('res.review-monitoring.index', ['deadline' => 'overdue']).'#review-monitoring-assignments')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalde4374013260732cd5542914186677e7)): ?>
<?php $attributes = $__attributesOriginalde4374013260732cd5542914186677e7; ?>
<?php unset($__attributesOriginalde4374013260732cd5542914186677e7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalde4374013260732cd5542914186677e7)): ?>
<?php $component = $__componentOriginalde4374013260732cd5542914186677e7; ?>
<?php unset($__componentOriginalde4374013260732cd5542914186677e7); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginalde4374013260732cd5542914186677e7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalde4374013260732cd5542914186677e7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.summary-card','data' => ['label' => 'Full Board Conflicts','count' => $metrics['conflicted_applications'],'icon' => 'alert-triangle','tone' => 'red','href' => route('res.review-monitoring.index').'#review-monitoring-conflicts']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.summary-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Full Board Conflicts','count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($metrics['conflicted_applications']),'icon' => 'alert-triangle','tone' => 'red','href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('res.review-monitoring.index').'#review-monitoring-conflicts')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalde4374013260732cd5542914186677e7)): ?>
<?php $attributes = $__attributesOriginalde4374013260732cd5542914186677e7; ?>
<?php unset($__attributesOriginalde4374013260732cd5542914186677e7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalde4374013260732cd5542914186677e7)): ?>
<?php $component = $__componentOriginalde4374013260732cd5542914186677e7; ?>
<?php unset($__componentOriginalde4374013260732cd5542914186677e7); ?>
<?php endif; ?>
        </div>

        <?php if($conflicts->isNotEmpty()): ?>
            <section
                class="review-monitoring-conflicts"
                id="review-monitoring-conflicts"
                aria-labelledby="review-monitoring-conflict-title"
                role="alert"
            >
                <header class="review-monitoring-section-heading">
                    <span class="review-monitoring-heading-icon" aria-hidden="true">
                        <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'alert-triangle','size' => '22']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'alert-triangle','size' => '22']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
                    </span>
                    <div>
                        <h2 id="review-monitoring-conflict-title">Full Board decision conflicts require RES attention</h2>
                        <p>Submitted outcomes disagree. Reviewer identities remain anonymous here; inspect the authorized read-only workspace before any release action.</p>
                    </div>
                    <?php if (isset($component)) { $__componentOriginal41cda017b93539193a6609497ccd9474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41cda017b93539193a6609497ccd9474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.status-badge','data' => ['label' => $metrics['conflicted_applications'].' unresolved','tone' => 'red']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($metrics['conflicted_applications'].' unresolved'),'tone' => 'red']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $attributes = $__attributesOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__attributesOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $component = $__componentOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__componentOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
                </header>

                <div class="review-monitoring-conflict-list">
                    <?php $__currentLoopData = $conflicts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $application): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $cycleAssignments = $application->reviewerAssignments
                                ->when(
                                    $application->review_consensus_cycle !== null,
                                    fn ($assignments) => $assignments->where('review_cycle', $application->review_consensus_cycle),
                                )
                                ->values();
                            if ($cycleAssignments->isEmpty()) {
                                $cycleAssignments = $application->reviewerAssignments->values();
                            }
                        ?>
                        <article class="review-monitoring-conflict-card">
                            <div class="review-monitoring-conflict-copy">
                                <span class="review-monitoring-code"><?php echo e($application->application_code); ?></span>
                                <h3><?php echo e($application->research_title); ?></h3>
                                <small>
                                    <?php echo e($application->review_conflicted_at?->format('M j, Y g:i A') ?? 'Conflict recorded'); ?>

                                    <?php if($application->review_consensus_cycle !== null): ?>
                                        &middot; Cycle <?php echo e($application->review_consensus_cycle); ?>

                                    <?php endif; ?>
                                </small>
                            </div>

                            <div class="review-monitoring-decisions" aria-label="Anonymous submitted decisions">
                                <?php $__currentLoopData = $cycleAssignments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php
                                        $decision = $assignment->reviewSubmission?->currentVersion?->decision
                                            ?? $assignment->reviewSubmission?->decision;
                                    ?>
                                    <div>
                                        <span>Reviewer <?php echo e($index + 1); ?></span>
                                        <?php if (isset($component)) { $__componentOriginal41cda017b93539193a6609497ccd9474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41cda017b93539193a6609497ccd9474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.status-badge','data' => ['label' => $decision?->label() ?? 'Awaiting Submission','tone' => $decision?->tone() ?? 'neutral']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($decision?->label() ?? 'Awaiting Submission'),'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($decision?->tone() ?? 'neutral')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $attributes = $__attributesOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__attributesOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $component = $__componentOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__componentOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>

                            <div class="review-monitoring-row-actions">
                                <a class="dashboard-outline-action" href="<?php echo e(route('res.applications.show', $application)); ?>">Application</a>
                                <a class="dashboard-primary-action" href="<?php echo e(route('res.certificates.workspace', $application)); ?>">Read-only Workspace</a>
                            </div>
                        </article>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </section>
        <?php else: ?>
            <section class="review-monitoring-no-conflicts" id="review-monitoring-conflicts" aria-label="Full Board consensus status">
                <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'check','size' => '21']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'check','size' => '21']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
                <div>
                    <strong>No unresolved Full Board conflicts</strong>
                    <span>Current submitted reviewer sets do not contain a recorded disagreement.</span>
                </div>
            </section>
        <?php endif; ?>

        <section class="application-panel review-monitoring-panel" id="review-monitoring-assignments" aria-labelledby="review-monitoring-table-title">
            <div class="application-panel-heading review-monitoring-panel-heading">
                <div>
                    <h2 id="review-monitoring-table-title">Assignment progress and deadlines</h2>
                    <p>Applicant identity and confidential reviewer comments are intentionally excluded from this operational view.</p>
                </div>
                <span><?php echo e($applications->total()); ?> application<?php echo e($applications->total() === 1 ? '' : 's'); ?></span>
            </div>

            <form class="review-monitoring-filters" method="GET" action="<?php echo e(route('res.review-monitoring.index')); ?>">
                <div class="application-field application-search-field review-monitoring-filter-search">
                    <label for="monitoring-q">Search</label>
                    <span><?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'search','size' => '18']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search','size' => '18']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?></span>
                    <input
                        id="monitoring-q"
                        name="q"
                        value="<?php echo e($filters['q'] ?? ''); ?>"
                        placeholder="Application code or research title"
                    >
                </div>

                <div class="application-field">
                    <label for="monitoring-review-type">Review Type</label>
                    <select id="monitoring-review-type" name="review_type">
                        <option value="">All review types</option>
                        <?php $__currentLoopData = $reviewTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reviewType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($reviewType->value); ?>" <?php if(($filters['review_type'] ?? '') === $reviewType->value): echo 'selected'; endif; ?>><?php echo e($reviewType->label()); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="application-field">
                    <label for="monitoring-assignment-status">Assignment Status</label>
                    <select id="monitoring-assignment-status" name="assignment_status">
                        <option value="">All assignment statuses</option>
                        <?php $__currentLoopData = $assignmentStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignmentStatus): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($assignmentStatus->value); ?>" <?php if(($filters['assignment_status'] ?? '') === $assignmentStatus->value): echo 'selected'; endif; ?>><?php echo e($assignmentStatus->label()); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="application-field">
                    <label for="monitoring-deadline">Deadline</label>
                    <select id="monitoring-deadline" name="deadline">
                        <option value="">All deadlines</option>
                        <option value="overdue" <?php if(($filters['deadline'] ?? '') === 'overdue'): echo 'selected'; endif; ?>>Overdue</option>
                        <option value="due_soon" <?php if(($filters['deadline'] ?? '') === 'due_soon'): echo 'selected'; endif; ?>>Due within 3 days</option>
                        <option value="on_track" <?php if(($filters['deadline'] ?? '') === 'on_track'): echo 'selected'; endif; ?>>More than 3 days</option>
                        <option value="no_deadline" <?php if(($filters['deadline'] ?? '') === 'no_deadline'): echo 'selected'; endif; ?>>No deadline</option>
                    </select>
                </div>

                <div class="application-field">
                    <label for="monitoring-consensus">Consensus</label>
                    <select id="monitoring-consensus" name="consensus">
                        <option value="">All consensus states</option>
                        <?php $__currentLoopData = $consensusStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $consensusStatus): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($consensusStatus->value); ?>" <?php if(($filters['consensus'] ?? '') === $consensusStatus->value): echo 'selected'; endif; ?>><?php echo e($consensusStatus->label()); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="review-monitoring-filter-actions">
                    <button class="dashboard-primary-action" type="submit">
                        <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'search','size' => '17']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search','size' => '17']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
                        <span>Apply Filters</span>
                    </button>
                    <a class="dashboard-outline-action" href="<?php echo e(route('res.review-monitoring.index')); ?>">Reset</a>
                </div>
            </form>

            <?php if($applications->isEmpty()): ?>
                <div class="review-monitoring-empty">
                    <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'file-search','size' => '34']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'file-search','size' => '34']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
                    <h3><?php echo e($hasReviewerFilters ? 'No assignments match these filters' : 'No reviewer assignments yet'); ?></h3>
                    <p><?php echo e($hasReviewerFilters ? 'Adjust the search or filters and try again.' : 'Applications appear here after RES assigns an eligible reviewer set.'); ?></p>
                </div>
            <?php else: ?>
                <?php if (isset($component)) { $__componentOriginal12fa1d1b5101a347e41acd744931d9c3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal12fa1d1b5101a347e41acd744931d9c3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.overflow','data' => ['class' => 'review-monitoring-table-region','label' => 'Review assignment progress records','wide' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.overflow'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'review-monitoring-table-region','label' => 'Review assignment progress records','wide' => true]); ?>
                    <table class="dashboard-table review-monitoring-table">
                        <thead>
                            <tr>
                                <th>Application</th>
                                <th>Review</th>
                                <th>Anonymous Assignment Progress</th>
                                <th>Next Deadline</th>
                                <th>Consensus</th>
                                <th class="dashboard-table-action">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $applications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $application): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $assignments = $application->reviewerAssignments->values();
                                    $reviewType = \App\Enums\ReviewType::tryFrom((string) $application->review_type);
                                    $required = max(1, $reviewType?->reviewerCount() ?? $assignments->count());
                                    $completed = $assignments->filter(fn ($assignment) => $assignment->assignment_status === \App\Enums\ReviewerAssignmentStatus::DecisionSubmitted)->count();
                                    $completion = min(100, (int) round(($completed / $required) * 100));
                                    $openAssignments = $assignments->reject(fn ($assignment) => $assignment->assignment_status === \App\Enums\ReviewerAssignmentStatus::DecisionSubmitted);
                                    $nextDeadline = $openAssignments
                                        ->filter(fn ($assignment) => $assignment->review_deadline_at !== null)
                                        ->sortBy('review_deadline_at')
                                        ->first()?->review_deadline_at;
                                    $deadlineTone = $nextDeadline?->isPast()
                                        ? 'red'
                                        : ($nextDeadline?->lte(now()->addDays(3)) ? 'orange' : 'blue');
                                ?>
                                <tr class="<?php echo e($application->review_consensus_status === \App\Enums\ReviewConsensusStatus::Conflicted ? 'is-conflicted' : ''); ?>">
                                    <td>
                                        <strong class="review-monitoring-code"><?php echo e($application->application_code); ?></strong>
                                        <a class="review-monitoring-title" href="<?php echo e(route('res.applications.show', $application)); ?>"><?php echo e($application->research_title); ?></a>
                                    </td>
                                    <td>
                                        <strong><?php echo e($reviewType?->label() ?? 'Review'); ?></strong>
                                        <small><?php echo e($assignments->first()?->review_type === 'revision_review' ? 'Revision cycle '.($assignments->first()?->review_cycle ?? 0) : 'Initial review'); ?></small>
                                    </td>
                                    <td>
                                        <div class="review-monitoring-progress-copy">
                                            <strong><?php echo e($completed); ?> of <?php echo e($required); ?> submitted</strong>
                                            <span><?php echo e($completion); ?>%</span>
                                        </div>
                                        <progress class="review-monitoring-progress" max="100" value="<?php echo e($completion); ?>"><?php echo e($completion); ?>%</progress>
                                        <div class="review-monitoring-assignment-chips" aria-label="Anonymous reviewer assignment statuses">
                                            <?php $__currentLoopData = $assignments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <span>
                                                    Reviewer <?php echo e($index + 1); ?>

                                                    <?php if (isset($component)) { $__componentOriginal41cda017b93539193a6609497ccd9474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41cda017b93539193a6609497ccd9474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.status-badge','data' => ['label' => $assignment->assignment_status->label(),'tone' => $assignment->assignment_status->tone()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($assignment->assignment_status->label()),'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($assignment->assignment_status->tone())]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $attributes = $__attributesOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__attributesOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $component = $__componentOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__componentOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
                                                </span>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($openAssignments->isEmpty()): ?>
                                            <?php if (isset($component)) { $__componentOriginal41cda017b93539193a6609497ccd9474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41cda017b93539193a6609497ccd9474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.status-badge','data' => ['label' => 'Completed','tone' => 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Completed','tone' => 'success']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $attributes = $__attributesOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__attributesOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $component = $__componentOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__componentOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
                                        <?php elseif($nextDeadline): ?>
                                            <?php if (isset($component)) { $__componentOriginal41cda017b93539193a6609497ccd9474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41cda017b93539193a6609497ccd9474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.status-badge','data' => ['label' => ($nextDeadline->isPast() ? 'Overdue · ' : '').$nextDeadline->format('M j, Y g:i A'),'tone' => $deadlineTone]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($nextDeadline->isPast() ? 'Overdue · ' : '').$nextDeadline->format('M j, Y g:i A')),'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($deadlineTone)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $attributes = $__attributesOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__attributesOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $component = $__componentOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__componentOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
                                        <?php else: ?>
                                            <?php if (isset($component)) { $__componentOriginal41cda017b93539193a6609497ccd9474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41cda017b93539193a6609497ccd9474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.status-badge','data' => ['label' => 'No deadline','tone' => 'neutral']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'No deadline','tone' => 'neutral']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $attributes = $__attributesOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__attributesOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $component = $__componentOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__componentOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($component)) { $__componentOriginal41cda017b93539193a6609497ccd9474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41cda017b93539193a6609497ccd9474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.status-badge','data' => ['label' => $application->review_consensus_status?->label() ?? 'Not evaluated','tone' => $application->review_consensus_status?->tone() ?? 'neutral']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($application->review_consensus_status?->label() ?? 'Not evaluated'),'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($application->review_consensus_status?->tone() ?? 'neutral')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $attributes = $__attributesOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__attributesOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $component = $__componentOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__componentOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
                                    </td>
                                    <td class="dashboard-table-action">
                                        <div class="review-monitoring-table-actions">
                                            <?php if (isset($component)) { $__componentOriginalaa74cdb70d0df392fd657d391d4e5055 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa74cdb70d0df392fd657d391d4e5055 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.action-link','data' => ['href' => route('res.applications.show', $application)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.action-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('res.applications.show', $application))]); ?>View <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalaa74cdb70d0df392fd657d391d4e5055)): ?>
<?php $attributes = $__attributesOriginalaa74cdb70d0df392fd657d391d4e5055; ?>
<?php unset($__attributesOriginalaa74cdb70d0df392fd657d391d4e5055); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalaa74cdb70d0df392fd657d391d4e5055)): ?>
<?php $component = $__componentOriginalaa74cdb70d0df392fd657d391d4e5055; ?>
<?php unset($__componentOriginalaa74cdb70d0df392fd657d391d4e5055); ?>
<?php endif; ?>
                                            <?php if (isset($component)) { $__componentOriginalaa74cdb70d0df392fd657d391d4e5055 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa74cdb70d0df392fd657d391d4e5055 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.action-link','data' => ['href' => route('res.applications.reviewers.index', $application)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.action-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('res.applications.reviewers.index', $application))]); ?>Reviewer Set <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalaa74cdb70d0df392fd657d391d4e5055)): ?>
<?php $attributes = $__attributesOriginalaa74cdb70d0df392fd657d391d4e5055; ?>
<?php unset($__attributesOriginalaa74cdb70d0df392fd657d391d4e5055); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalaa74cdb70d0df392fd657d391d4e5055)): ?>
<?php $component = $__componentOriginalaa74cdb70d0df392fd657d391d4e5055; ?>
<?php unset($__componentOriginalaa74cdb70d0df392fd657d391d4e5055); ?>
<?php endif; ?>
                                            <?php if (isset($component)) { $__componentOriginalaa74cdb70d0df392fd657d391d4e5055 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa74cdb70d0df392fd657d391d4e5055 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.action-link','data' => ['href' => route('res.certificates.workspace', $application)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.action-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('res.certificates.workspace', $application))]); ?>Workspace <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalaa74cdb70d0df392fd657d391d4e5055)): ?>
<?php $attributes = $__attributesOriginalaa74cdb70d0df392fd657d391d4e5055; ?>
<?php unset($__attributesOriginalaa74cdb70d0df392fd657d391d4e5055); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalaa74cdb70d0df392fd657d391d4e5055)): ?>
<?php $component = $__componentOriginalaa74cdb70d0df392fd657d391d4e5055; ?>
<?php unset($__componentOriginalaa74cdb70d0df392fd657d391d4e5055); ?>
<?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal12fa1d1b5101a347e41acd744931d9c3)): ?>
<?php $attributes = $__attributesOriginal12fa1d1b5101a347e41acd744931d9c3; ?>
<?php unset($__attributesOriginal12fa1d1b5101a347e41acd744931d9c3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal12fa1d1b5101a347e41acd744931d9c3)): ?>
<?php $component = $__componentOriginal12fa1d1b5101a347e41acd744931d9c3; ?>
<?php unset($__componentOriginal12fa1d1b5101a347e41acd744931d9c3); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal7474fa32f1c84a0cd13283f0ce6d74ec = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7474fa32f1c84a0cd13283f0ce6d74ec = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.pagination','data' => ['paginator' => $applications,'label' => 'Review monitoring application pages']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.pagination'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['paginator' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($applications),'label' => 'Review monitoring application pages']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7474fa32f1c84a0cd13283f0ce6d74ec)): ?>
<?php $attributes = $__attributesOriginal7474fa32f1c84a0cd13283f0ce6d74ec; ?>
<?php unset($__attributesOriginal7474fa32f1c84a0cd13283f0ce6d74ec); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7474fa32f1c84a0cd13283f0ce6d74ec)): ?>
<?php $component = $__componentOriginal7474fa32f1c84a0cd13283f0ce6d74ec; ?>
<?php unset($__componentOriginal7474fa32f1c84a0cd13283f0ce6d74ec); ?>
<?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="application-panel review-monitoring-panel" id="review-monitoring-capacity" aria-labelledby="review-monitoring-capacity-title">
            <div class="application-panel-heading review-monitoring-panel-heading">
                <div>
                    <h2 id="review-monitoring-capacity-title">Reviewer-enabled Adviser workload</h2>
                    <p>Active load and declared capacity only. Application decisions and confidential comments are not shown.</p>
                </div>
                <span><?php echo e($reviewerWorkloads->total()); ?> enabled</span>
            </div>

            <?php if($reviewerWorkloads->isEmpty()): ?>
                <div class="review-monitoring-empty is-compact">
                    <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'users','size' => '32']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'users','size' => '32']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
                    <h3>No reviewer-enabled Advisers</h3>
                    <p>Capacity records appear after an active Adviser receives reviewer capability.</p>
                </div>
            <?php else: ?>
                <div class="review-monitoring-workload-grid">
                    <?php $__currentLoopData = $reviewerWorkloads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reviewer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $capacity = max(0, (int) ($reviewer->reviewer_capacity ?? 0));
                            $activeLoad = (int) $reviewer->active_assignment_count;
                            $atCapacity = $capacity < 1 || $activeLoad >= $capacity;
                            $utilization = $capacity > 0 ? min(100, (int) round(($activeLoad / $capacity) * 100)) : 100;
                            $classifications = $reviewer->reviewerClassificationLabels();
                        ?>
                        <article class="review-monitoring-workload-card <?php echo e($atCapacity ? 'is-full' : ''); ?>">
                            <header>
                                <div>
                                    <h3><?php echo e($reviewer->name); ?></h3>
                                    <p><?php echo e($reviewer->position_title ?: 'Adviser Reviewer'); ?><?php echo e($reviewer->department ? ' - '.$reviewer->department : ''); ?></p>
                                </div>
                                <?php if (isset($component)) { $__componentOriginal41cda017b93539193a6609497ccd9474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41cda017b93539193a6609497ccd9474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.status-badge','data' => ['label' => $atCapacity ? 'At capacity' : 'Available','tone' => $atCapacity ? 'red' : 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($atCapacity ? 'At capacity' : 'Available'),'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($atCapacity ? 'red' : 'success')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $attributes = $__attributesOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__attributesOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $component = $__componentOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__componentOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
                            </header>

                            <div class="review-monitoring-capacity-copy">
                                <strong><?php echo e($activeLoad); ?> / <?php echo e($capacity); ?></strong>
                                <span>active assignments</span>
                            </div>
                            <progress class="review-monitoring-progress" max="100" value="<?php echo e($utilization); ?>"><?php echo e($utilization); ?>%</progress>

                            <footer>
                                <div class="review-monitoring-classifications" aria-label="Reviewer classifications">
                                    <?php $__empty_1 = true; $__currentLoopData = $classifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $classification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <span><?php echo e($classification); ?></span>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <span>Unclassified</span>
                                    <?php endif; ?>
                                </div>
                                <?php if((int) $reviewer->overdue_assignment_count > 0): ?>
                                    <?php if (isset($component)) { $__componentOriginal41cda017b93539193a6609497ccd9474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41cda017b93539193a6609497ccd9474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.status-badge','data' => ['label' => $reviewer->overdue_assignment_count.' overdue','tone' => 'orange']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reviewer->overdue_assignment_count.' overdue'),'tone' => 'orange']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $attributes = $__attributesOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__attributesOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $component = $__componentOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__componentOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
                                <?php endif; ?>
                            </footer>
                        </article>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <?php if (isset($component)) { $__componentOriginal7474fa32f1c84a0cd13283f0ce6d74ec = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7474fa32f1c84a0cd13283f0ce6d74ec = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.pagination','data' => ['paginator' => $reviewerWorkloads,'label' => 'Reviewer workload pages']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.pagination'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['paginator' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reviewerWorkloads),'label' => 'Reviewer workload pages']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7474fa32f1c84a0cd13283f0ce6d74ec)): ?>
<?php $attributes = $__attributesOriginal7474fa32f1c84a0cd13283f0ce6d74ec; ?>
<?php unset($__attributesOriginal7474fa32f1c84a0cd13283f0ce6d74ec); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7474fa32f1c84a0cd13283f0ce6d74ec)): ?>
<?php $component = $__componentOriginal7474fa32f1c84a0cd13283f0ce6d74ec; ?>
<?php unset($__componentOriginal7474fa32f1c84a0cd13283f0ce6d74ec); ?>
<?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="application-panel review-monitoring-panel" id="review-monitoring-advisers" aria-labelledby="review-monitoring-advisers-title">
            <div class="application-panel-heading review-monitoring-panel-heading">
                <div>
                    <h2 id="review-monitoring-advisers-title">Adviser endorsement workload</h2>
                    <p>Compare declared expectations with live endorsement records and received applications. Applicant identity and credentials are excluded.</p>
                </div>
                <span><?php echo e($adviserWorkloads->total()); ?> Adviser<?php echo e($adviserWorkloads->total() === 1 ? '' : 's'); ?></span>
            </div>

            <form class="review-monitoring-adviser-filters" method="GET" action="<?php echo e(route('res.review-monitoring.index')); ?>">
                <div class="application-field application-search-field review-monitoring-filter-search">
                    <label for="monitoring-adviser-q">Search Adviser</label>
                    <span><?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'search','size' => '18']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search','size' => '18']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?></span>
                    <input
                        id="monitoring-adviser-q"
                        name="adviser_q"
                        value="<?php echo e($filters['adviser_q'] ?? ''); ?>"
                        placeholder="Name, position, or department"
                    >
                </div>

                <div class="application-field">
                    <label for="monitoring-adviser-department">Department</label>
                    <select id="monitoring-adviser-department" name="adviser_department">
                        <option value="">All departments</option>
                        <?php $__currentLoopData = $adviserDepartments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $department): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($department); ?>" <?php if(($filters['adviser_department'] ?? '') === $department): echo 'selected'; endif; ?>><?php echo e($department); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="application-field">
                    <label for="monitoring-adviser-workload">Workload State</label>
                    <select id="monitoring-adviser-workload" name="adviser_workload">
                        <option value="">All workload states</option>
                        <option value="awaiting_action" <?php if(($filters['adviser_workload'] ?? '') === 'awaiting_action'): echo 'selected'; endif; ?>>Awaiting Adviser action</option>
                        <option value="remaining_expected" <?php if(($filters['adviser_workload'] ?? '') === 'remaining_expected'): echo 'selected'; endif; ?>>Expected workload remaining</option>
                        <option value="not_received" <?php if(($filters['adviser_workload'] ?? '') === 'not_received'): echo 'selected'; endif; ?>>Not yet received</option>
                        <option value="target_met" <?php if(($filters['adviser_workload'] ?? '') === 'target_met'): echo 'selected'; endif; ?>>Target met</option>
                        <option value="no_target" <?php if(($filters['adviser_workload'] ?? '') === 'no_target'): echo 'selected'; endif; ?>>No declared target</option>
                    </select>
                </div>

                <div class="review-monitoring-filter-actions">
                    <button class="dashboard-primary-action" type="submit">
                        <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'search','size' => '17']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search','size' => '17']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
                        <span>Apply Filters</span>
                    </button>
                    <a class="dashboard-outline-action" href="<?php echo e(route('res.review-monitoring.index').'#review-monitoring-advisers'); ?>">Reset</a>
                </div>
            </form>

            <?php if($adviserWorkloads->isEmpty()): ?>
                <div class="review-monitoring-empty is-compact">
                    <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'user-check','size' => '32']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'user-check','size' => '32']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
                    <h3><?php echo e($hasAdviserFilters ? 'No Advisers match these filters' : 'No authorized Advisers found'); ?></h3>
                    <p><?php echo e($hasAdviserFilters ? 'Adjust the Adviser search or workload filters and try again.' : 'Active Adviser accounts appear here when their endorsement workload is available.'); ?></p>
                </div>
            <?php else: ?>
                <?php if (isset($component)) { $__componentOriginal12fa1d1b5101a347e41acd744931d9c3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal12fa1d1b5101a347e41acd744931d9c3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.overflow','data' => ['class' => 'review-monitoring-adviser-table-region','label' => 'Adviser endorsement workload records','wide' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.overflow'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'review-monitoring-adviser-table-region','label' => 'Adviser endorsement workload records','wide' => true]); ?>
                    <table class="dashboard-table review-monitoring-adviser-table">
                        <thead>
                            <tr>
                                <th>Authorized Adviser</th>
                                <th>Declared Expected</th>
                                <th>Completed Endorsements</th>
                                <th>Received, Awaiting Endorsement</th>
                                <th>Remaining Expected</th>
                                <th>Not Yet Received</th>
                                <th>Application Progress / Drill-down</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $adviserWorkloads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $adviser): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $statistics = $adviser->endorsement_statistics ?? [
                                        'declared' => 0,
                                        'endorsed' => 0,
                                        'awaiting' => 0,
                                        'remaining' => 0,
                                        'not_received' => 0,
                                    ];
                                    $declared = (int) $statistics['declared'];
                                    $endorsed = (int) $statistics['endorsed'];
                                    $awaiting = (int) $statistics['awaiting'];
                                    $completion = $declared > 0
                                        ? min(100, (int) round(($endorsed / $declared) * 100))
                                        : 0;
                                ?>
                                <tr
                                    data-adviser-workload-row="<?php echo e($adviser->id); ?>"
                                    data-declared="<?php echo e($declared); ?>"
                                    data-endorsed="<?php echo e($endorsed); ?>"
                                    data-awaiting="<?php echo e($awaiting); ?>"
                                    data-remaining="<?php echo e($statistics['remaining']); ?>"
                                    data-not-received="<?php echo e($statistics['not_received']); ?>"
                                >
                                    <td>
                                        <strong><?php echo e($adviser->name); ?></strong>
                                        <small><?php echo e($adviser->position_title ?: 'Research Adviser'); ?><?php echo e($adviser->department ? ' - '.$adviser->department : ''); ?></small>
                                    </td>
                                    <td><strong class="review-monitoring-stat-value"><?php echo e($declared); ?></strong></td>
                                    <td>
                                        <strong class="review-monitoring-stat-value tone-green"><?php echo e($endorsed); ?></strong>
                                        <progress class="review-monitoring-progress" max="100" value="<?php echo e($completion); ?>"><?php echo e($completion); ?>%</progress>
                                        <small><?php echo e($completion); ?>% of declared expectation</small>
                                    </td>
                                    <td>
                                        <?php if (isset($component)) { $__componentOriginal41cda017b93539193a6609497ccd9474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41cda017b93539193a6609497ccd9474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.status-badge','data' => ['label' => $awaiting.' received','tone' => $awaiting > 0 ? 'orange' : 'neutral']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($awaiting.' received'),'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($awaiting > 0 ? 'orange' : 'neutral')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $attributes = $__attributesOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__attributesOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $component = $__componentOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__componentOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
                                    </td>
                                    <td><strong class="review-monitoring-stat-value tone-violet"><?php echo e($statistics['remaining']); ?></strong></td>
                                    <td>
                                        <?php if (isset($component)) { $__componentOriginal41cda017b93539193a6609497ccd9474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41cda017b93539193a6609497ccd9474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.status-badge','data' => ['label' => $statistics['not_received'].' not received','tone' => $statistics['not_received'] > 0 ? 'blue' : 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($statistics['not_received'].' not received'),'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($statistics['not_received'] > 0 ? 'blue' : 'success')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $attributes = $__attributesOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__attributesOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $component = $__componentOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__componentOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($adviser->advisedApplications->isEmpty()): ?>
                                            <span class="review-monitoring-no-applications">No applications received</span>
                                        <?php else: ?>
                                            <details class="review-monitoring-application-drilldown">
                                                <summary>
                                                    <span><?php echo e($adviser->advisedApplications->count()); ?> recent application<?php echo e($adviser->advisedApplications->count() === 1 ? '' : 's'); ?></span>
                                                    <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'chevron-down','size' => '16']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevron-down','size' => '16']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
                                                </summary>
                                                <ul>
                                                    <?php $__currentLoopData = $adviser->advisedApplications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $application): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <li>
                                                            <a href="<?php echo e(route('res.applications.show', $application)); ?>"><?php echo e($application->application_code); ?></a>
                                                            <?php if (isset($component)) { $__componentOriginal41cda017b93539193a6609497ccd9474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41cda017b93539193a6609497ccd9474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.status-badge','data' => ['label' => $application->application_status->label(),'tone' => $application->application_status->tone()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($application->application_status->label()),'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($application->application_status->tone())]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $attributes = $__attributesOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__attributesOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41cda017b93539193a6609497ccd9474)): ?>
<?php $component = $__componentOriginal41cda017b93539193a6609497ccd9474; ?>
<?php unset($__componentOriginal41cda017b93539193a6609497ccd9474); ?>
<?php endif; ?>
                                                        </li>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </ul>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal12fa1d1b5101a347e41acd744931d9c3)): ?>
<?php $attributes = $__attributesOriginal12fa1d1b5101a347e41acd744931d9c3; ?>
<?php unset($__attributesOriginal12fa1d1b5101a347e41acd744931d9c3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal12fa1d1b5101a347e41acd744931d9c3)): ?>
<?php $component = $__componentOriginal12fa1d1b5101a347e41acd744931d9c3; ?>
<?php unset($__componentOriginal12fa1d1b5101a347e41acd744931d9c3); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal7474fa32f1c84a0cd13283f0ce6d74ec = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7474fa32f1c84a0cd13283f0ce6d74ec = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.pagination','data' => ['paginator' => $adviserWorkloads,'label' => 'Adviser endorsement workload pages']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.pagination'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['paginator' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($adviserWorkloads),'label' => 'Adviser endorsement workload pages']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7474fa32f1c84a0cd13283f0ce6d74ec)): ?>
<?php $attributes = $__attributesOriginal7474fa32f1c84a0cd13283f0ce6d74ec; ?>
<?php unset($__attributesOriginal7474fa32f1c84a0cd13283f0ce6d74ec); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7474fa32f1c84a0cd13283f0ce6d74ec)): ?>
<?php $component = $__componentOriginal7474fa32f1c84a0cd13283f0ce6d74ec; ?>
<?php unset($__componentOriginal7474fa32f1c84a0cd13283f0ce6d74ec); ?>
<?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Ace Arthur\OneDrive\Documents\GitHub\Ethics-Certification-Research-Application-Tracking-System\resources\views/dashboard/reviews/res-monitoring.blade.php ENDPATH**/ ?>