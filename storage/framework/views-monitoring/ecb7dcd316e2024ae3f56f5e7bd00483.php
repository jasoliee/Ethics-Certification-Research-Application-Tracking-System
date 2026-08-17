<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label', 'tone' => 'neutral', 'dot' => false]));

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

foreach (array_filter((['label', 'tone' => 'neutral', 'dot' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<span <?php echo e($attributes->class(['dashboard-status-badge', 'tone-'.$tone])); ?>>
    <?php if($dot): ?><span class="dashboard-status-dot" aria-hidden="true"></span><?php endif; ?>
    <?php echo e($label); ?>

</span>
<?php /**PATH C:\Users\Ace Arthur\OneDrive\Documents\GitHub\Ethics-Certification-Research-Application-Tracking-System\resources\views/components/dashboard/status-badge.blade.php ENDPATH**/ ?>