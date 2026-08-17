<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title' => 'Dashboard', 'breadcrumbs' => []]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['title' => 'Dashboard', 'breadcrumbs' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<header class="dashboard-topbar">
    <div class="dashboard-topbar-heading">
        <button class="dashboard-menu-button" type="button" aria-label="Open navigation" aria-controls="dashboard-sidebar" aria-expanded="false" data-sidebar-open>
            <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'menu']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'menu']); ?>
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
        </button>
        <?php if(count($breadcrumbs) > 0): ?>
            <?php if (isset($component)) { $__componentOriginalc60db44e52d942d3b0e4d76f7065f04b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc60db44e52d942d3b0e4d76f7065f04b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.breadcrumbs','data' => ['items' => $breadcrumbs]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.breadcrumbs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($breadcrumbs)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc60db44e52d942d3b0e4d76f7065f04b)): ?>
<?php $attributes = $__attributesOriginalc60db44e52d942d3b0e4d76f7065f04b; ?>
<?php unset($__attributesOriginalc60db44e52d942d3b0e4d76f7065f04b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc60db44e52d942d3b0e4d76f7065f04b)): ?>
<?php $component = $__componentOriginalc60db44e52d942d3b0e4d76f7065f04b; ?>
<?php unset($__componentOriginalc60db44e52d942d3b0e4d76f7065f04b); ?>
<?php endif; ?>
        <?php else: ?>
            <span class="dashboard-topbar-title"><?php echo e($title); ?></span>
        <?php endif; ?>
    </div>

    <div class="dashboard-topbar-actions">
        <?php if(request()->routeIs('dashboard')): ?>
            <button class="dashboard-guide-button" type="button" data-guide-open <?php if($dashboardRequiresOnboarding): ?> hidden <?php endif; ?>>
                <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'circle-help','size' => '19']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'circle-help','size' => '19']); ?>
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
                <span>Guide</span>
            </button>
        <?php endif; ?>
        <div class="dashboard-menu-wrap">
            <button
                class="dashboard-icon-button"
                type="button"
                aria-label="Open notifications"
                aria-controls="dashboard-notification-menu"
                aria-expanded="false"
                data-menu-toggle="notifications"
            >
                <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'bell']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bell']); ?>
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
                <?php if($dashboardUnreadCount > 0): ?>
                    <span class="dashboard-notification-count" aria-label="<?php echo e($dashboardUnreadCount); ?> unread notifications">
                        <?php echo e($dashboardUnreadCount > 9 ? '9+' : $dashboardUnreadCount); ?>

                    </span>
                <?php endif; ?>
            </button>

            <section class="dashboard-dropdown dashboard-notification-menu" id="dashboard-notification-menu" data-menu="notifications" hidden>
                <div class="dashboard-dropdown-header">
                    <strong>Notifications</strong>
                    <?php if($dashboardUnreadCount > 0): ?>
                        <form method="POST" action="<?php echo e(route('notifications.mark-all-read')); ?>">
                            <?php echo csrf_field(); ?>
                            <button class="dashboard-text-button" type="submit">Mark all as read</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php $__empty_1 = true; $__currentLoopData = $dashboardNotifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <a class="dashboard-notification-item <?php echo e($notification['unread'] ? 'is-unread' : ''); ?>" href="<?php echo e($notification['url']); ?>">
                        <span class="dashboard-notification-icon tone-<?php echo e($notification['tone']); ?>">
                            <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => $notification['icon'],'size' => '20']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($notification['icon']),'size' => '20']); ?>
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
                        <span class="dashboard-notification-copy">
                            <strong><?php echo e($notification['title']); ?></strong>
                            <span><?php echo e($notification['message']); ?></span>
                            <small><?php echo e($notification['time']); ?></small>
                        </span>
                        <?php if($notification['unread']): ?>
                            <span class="dashboard-unread-dot" aria-label="Unread"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="dashboard-dropdown-empty">
                        <img class="dashboard-empty-asset" src="<?php echo e(asset('assets/empty-states/no-notifications.png')); ?>" alt="Empty notification bell">
                        <strong>No notifications yet</strong>
                        <span>You will see application and review updates here.</span>
                    </div>
                <?php endif; ?>

                <a class="dashboard-dropdown-footer" href="<?php echo e(route($dashboardNotificationsRoute)); ?>">View all notifications</a>
            </section>
        </div>

        <div class="dashboard-menu-wrap">
            <button
                class="dashboard-profile-button"
                type="button"
                aria-label="Open profile menu"
                aria-controls="dashboard-profile-menu"
                aria-expanded="false"
                data-menu-toggle="profile"
            >
                <span class="dashboard-avatar" aria-hidden="true"><?php echo e($dashboardUserInitials); ?></span>
                <span class="dashboard-profile-name"><?php echo e(auth()->user()->name); ?></span>
                <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'chevron-down','size' => '18']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevron-down','size' => '18']); ?>
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
            </button>

            <div class="dashboard-dropdown dashboard-profile-menu" id="dashboard-profile-menu" data-menu="profile" hidden>
                <a href="<?php echo e(route($dashboardSettingsRoute)); ?>">
                    <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'settings','size' => '19']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'settings','size' => '19']); ?>
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
                    <span>Settings</span>
                </a>
                <form method="POST" action="<?php echo e(route('logout')); ?>">
                    <?php echo csrf_field(); ?>
                    <button type="submit">
                        <?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'logout','size' => '19']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'logout','size' => '19']); ?>
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
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
<?php /**PATH C:\Users\Ace Arthur\OneDrive\Documents\GitHub\Ethics-Certification-Research-Application-Tracking-System\resources\views/components/dashboard/topbar.blade.php ENDPATH**/ ?>