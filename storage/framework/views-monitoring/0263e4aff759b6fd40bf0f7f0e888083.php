<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['paginator', 'label' => 'Result pages']));

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

foreach (array_filter((['paginator', 'label' => 'Result pages']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php if($paginator->hasPages()): ?>
    <?php
        $startPage = max(1, $paginator->currentPage() - 2);
        $endPage = min($paginator->lastPage(), $paginator->currentPage() + 2);
    ?>
    <nav <?php echo e($attributes->class(['identity-pagination'])); ?> aria-label="<?php echo e($label); ?>">
        <?php if($paginator->onFirstPage()): ?>
            <span class="identity-pagination-direction" aria-disabled="true">Previous</span>
        <?php else: ?>
            <a class="identity-pagination-direction" href="<?php echo e($paginator->previousPageUrl()); ?>" rel="prev">Previous</a>
        <?php endif; ?>

        <div class="identity-pagination-pages">
            <?php if($startPage > 1): ?>
                <a href="<?php echo e($paginator->url(1)); ?>" aria-label="Go to page 1">1</a>
                <?php if($startPage > 2): ?><span class="identity-pagination-ellipsis" aria-hidden="true">&hellip;</span><?php endif; ?>
            <?php endif; ?>

            <?php $__currentLoopData = range($startPage, $endPage); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($page === $paginator->currentPage()): ?>
                    <span class="is-current" aria-current="page"><?php echo e($page); ?></span>
                <?php else: ?>
                    <a href="<?php echo e($paginator->url($page)); ?>" aria-label="Go to page <?php echo e($page); ?>"><?php echo e($page); ?></a>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <?php if($endPage < $paginator->lastPage()): ?>
                <?php if($endPage < $paginator->lastPage() - 1): ?><span class="identity-pagination-ellipsis" aria-hidden="true">&hellip;</span><?php endif; ?>
                <a href="<?php echo e($paginator->url($paginator->lastPage())); ?>" aria-label="Go to page <?php echo e($paginator->lastPage()); ?>"><?php echo e($paginator->lastPage()); ?></a>
            <?php endif; ?>
        </div>

        <?php if($paginator->hasMorePages()): ?>
            <a class="identity-pagination-direction" href="<?php echo e($paginator->nextPageUrl()); ?>" rel="next">Next</a>
        <?php else: ?>
            <span class="identity-pagination-direction" aria-disabled="true">Next</span>
        <?php endif; ?>
    </nav>
<?php endif; ?>
<?php /**PATH C:\Users\Ace Arthur\OneDrive\Documents\GitHub\Ethics-Certification-Research-Application-Tracking-System\resources\views/components/dashboard/pagination.blade.php ENDPATH**/ ?>