<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['guide', 'requiresCompletion' => false]));

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

foreach (array_filter((['guide', 'requiresCompletion' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<section
    class="dashboard-onboarding"
    data-onboarding-guide
    data-requires-completion="<?php echo e($requiresCompletion ? 'true' : 'false'); ?>"
    data-complete-url="<?php echo e(route('onboarding.complete')); ?>"
    hidden
>
    <div class="dashboard-onboarding-dialog" role="dialog" aria-modal="true" aria-labelledby="onboarding-title" tabindex="-1">
        <header>
            <span class="dashboard-onboarding-icon"><?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'clipboard','size' => '24']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'clipboard','size' => '24']); ?>
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
            <div>
                <span>Getting Started</span>
                <h2 id="onboarding-title"><?php echo e($guide['title']); ?></h2>
            </div>
            <button type="button" aria-label="Close guide" data-guide-close><?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => 'x','size' => '20']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'x','size' => '20']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $attributes = $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19)): ?>
<?php $component = $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19; ?>
<?php unset($__componentOriginald4af31a9fbcc7c391a6cfd9546254e19); ?>
<?php endif; ?></button>
        </header>

        <p><?php echo e($guide['introduction']); ?></p>
        <ol>
            <?php $__currentLoopData = $guide['steps']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li><span><?php echo e($loop->iteration); ?></span><div><strong><?php echo e($step['title']); ?></strong><p><?php echo e($step['description']); ?></p></div></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ol>
        <p class="dashboard-onboarding-support"><?php echo e($guide['support']); ?></p>
        <footer><button class="dashboard-primary-action" type="button" data-guide-finish>Finish Guide</button></footer>
    </div>
</section>
<?php /**PATH C:\Users\Ace Arthur\OneDrive\Documents\GitHub\Ethics-Certification-Research-Application-Tracking-System\resources\views/components/dashboard/onboarding-guide.blade.php ENDPATH**/ ?>