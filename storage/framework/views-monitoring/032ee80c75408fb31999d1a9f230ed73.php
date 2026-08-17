<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo e($pageTitle ?? 'Dashboard'); ?> | ECRATS</title>
    <link rel="icon" type="image/png" href="<?php echo e(Vite::asset('assets/logo-256.png')); ?>">

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="ecrats-dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
        <?php if (isset($component)) { $__componentOriginal060abe2a9b4511e378911474e77b046d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal060abe2a9b4511e378911474e77b046d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.sidebar','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.sidebar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal060abe2a9b4511e378911474e77b046d)): ?>
<?php $attributes = $__attributesOriginal060abe2a9b4511e378911474e77b046d; ?>
<?php unset($__attributesOriginal060abe2a9b4511e378911474e77b046d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal060abe2a9b4511e378911474e77b046d)): ?>
<?php $component = $__componentOriginal060abe2a9b4511e378911474e77b046d; ?>
<?php unset($__componentOriginal060abe2a9b4511e378911474e77b046d); ?>
<?php endif; ?>

        <div class="dashboard-sidebar-backdrop" data-sidebar-backdrop hidden></div>

        <div class="dashboard-workspace">
            <?php if (isset($component)) { $__componentOriginal1185a77f86785c5182eccccf9103cfa0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1185a77f86785c5182eccccf9103cfa0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.topbar','data' => ['title' => $pageTitle ?? 'Dashboard','breadcrumbs' => $breadcrumbs ?? []]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.topbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pageTitle ?? 'Dashboard'),'breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($breadcrumbs ?? [])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1185a77f86785c5182eccccf9103cfa0)): ?>
<?php $attributes = $__attributesOriginal1185a77f86785c5182eccccf9103cfa0; ?>
<?php unset($__attributesOriginal1185a77f86785c5182eccccf9103cfa0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1185a77f86785c5182eccccf9103cfa0)): ?>
<?php $component = $__componentOriginal1185a77f86785c5182eccccf9103cfa0; ?>
<?php unset($__componentOriginal1185a77f86785c5182eccccf9103cfa0); ?>
<?php endif; ?>

            <main class="dashboard-content" id="main-content" tabindex="-1">
                <?php if(session('status')): ?>
                    <div class="dashboard-flash" role="status">
                        <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'check']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'check']); ?>
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
                        <span><?php echo e(session('status')); ?></span>
                    </div>
                <?php endif; ?>

                <?php echo $__env->yieldContent('content'); ?>

                <?php if (isset($component)) { $__componentOriginal6707a3bc0f88f683c07a4c9a4fb747c2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6707a3bc0f88f683c07a4c9a4fb747c2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.onboarding-guide','data' => ['guide' => $dashboardOnboardingGuide,'requiresCompletion' => $dashboardRequiresOnboarding]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.onboarding-guide'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['guide' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($dashboardOnboardingGuide),'requires-completion' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($dashboardRequiresOnboarding)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6707a3bc0f88f683c07a4c9a4fb747c2)): ?>
<?php $attributes = $__attributesOriginal6707a3bc0f88f683c07a4c9a4fb747c2; ?>
<?php unset($__attributesOriginal6707a3bc0f88f683c07a4c9a4fb747c2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6707a3bc0f88f683c07a4c9a4fb747c2)): ?>
<?php $component = $__componentOriginal6707a3bc0f88f683c07a4c9a4fb747c2; ?>
<?php unset($__componentOriginal6707a3bc0f88f683c07a4c9a4fb747c2); ?>
<?php endif; ?>
            </main>

            <?php if (isset($component)) { $__componentOriginal6131d733ccfb7ef2e4ea10b2ead2ef15 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6131d733ccfb7ef2e4ea10b2ead2ef15 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.footer','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.footer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6131d733ccfb7ef2e4ea10b2ead2ef15)): ?>
<?php $attributes = $__attributesOriginal6131d733ccfb7ef2e4ea10b2ead2ef15; ?>
<?php unset($__attributesOriginal6131d733ccfb7ef2e4ea10b2ead2ef15); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6131d733ccfb7ef2e4ea10b2ead2ef15)): ?>
<?php $component = $__componentOriginal6131d733ccfb7ef2e4ea10b2ead2ef15; ?>
<?php unset($__componentOriginal6131d733ccfb7ef2e4ea10b2ead2ef15); ?>
<?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php /**PATH C:\Users\Ace Arthur\OneDrive\Documents\GitHub\Ethics-Certification-Research-Application-Tracking-System\resources\views/layouts/dashboard.blade.php ENDPATH**/ ?>