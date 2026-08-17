<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label', 'count', 'icon', 'tone' => 'green', 'href']));

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

foreach (array_filter((['label', 'count', 'icon', 'tone' => 'green', 'href']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>


<a class="dashboard-summary-card tone-<?php echo e($tone); ?>" href="<?php echo e($href); ?>" aria-label="<?php echo e($label); ?>: <?php echo e($count); ?>">
    
    <span class="dashboard-summary-icon"><?php if (isset($component)) { $__componentOriginald4af31a9fbcc7c391a6cfd9546254e19 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4af31a9fbcc7c391a6cfd9546254e19 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard.icon','data' => ['name' => $icon,'size' => '31']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'size' => '31']); ?>
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
    
    <span class="dashboard-summary-copy">
        <strong><?php echo e($count); ?></strong>
        <span><?php echo e($label); ?></span>
    </span>
</a>
<?php /**PATH C:\Users\Ace Arthur\OneDrive\Documents\GitHub\Ethics-Certification-Research-Application-Tracking-System\resources\views/components/dashboard/summary-card.blade.php ENDPATH**/ ?>