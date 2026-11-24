<?php $__env->startSection('content'); ?>
<h1>Maps</h1>
<p class="lead">Map packages exported by the Blender pipeline. Open one in the 3D editor to place shop signs and advertisements. Only <b>published</b> maps are visible through the API.</p>

<div class="card">
    <h2>Import a map package</h2>
    <?php if($packages === []): ?>
        <p class="muted">No packages found under <code><?php echo e($importRoot); ?></code>. Export one with <code>tools/blender/ism_map.py export --out tools/blender/work/&lt;name&gt;/package</code>, or use <code>php artisan map:import &lt;folder&gt;</code>.</p>
    <?php else: ?>
        <form method="post" action="<?php echo e(route('admin.maps.import')); ?>" class="grid">
            <?php echo csrf_field(); ?>
            <label>Package folder (under the Blender work folder)
                <select name="folder" required>
                    <?php $__currentLoopData = $packages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($package); ?>" <?php if(old('folder') === $package): echo 'selected'; endif; ?>><?php echo e($package); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>
            <label>Name <input type="text" name="name" value="<?php echo e(old('name', 'Sample floor')); ?>" maxlength="120" required></label>
            <label class="check"><input type="checkbox" name="publish" value="1" <?php if(old('publish')): echo 'checked'; endif; ?>> Publish to the API now</label>
            <div><button class="primary" type="submit">Import</button></div>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Imported maps</h2>
    <?php if($maps->isEmpty()): ?>
        <p class="muted">None yet.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Name</th><th>Status</th><th>Contents</th><th></th></tr></thead>
            <tbody>
            <?php $__currentLoopData = $maps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $map): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td>
                        <div><?php echo e($map->name); ?></div>
                        <div class="muted" style="font-size:12px"><code><?php echo e($map->id); ?></code></div>
                    </td>
                    <td><span class="badge <?php echo e($map->isPublished() ? 'ok' : 'off'); ?>"><?php echo e($map->isPublished() ? 'published' : 'draft'); ?></span></td>
                    <td class="muted">
                        floors <?php echo e(implode(', ', $map->floor_codes)); ?> · <?php echo e($map->stats['nodes'] ?? 0); ?> nodes · <?php echo e($map->stats['checkpoints'] ?? 0); ?> QR codes<br>
                        <?php echo e($map->annotations_count); ?> signs and advertisements · exported <?php echo e($map->package_generated_at?->format('Y-m-d H:i') ?? 'unknown'); ?>

                    </td>
                    <td class="actions">
                        <a class="button primary" href="<?php echo e(route('admin.maps.show', $map)); ?>">Open 3D editor</a>
                        <form method="post" action="<?php echo e(route('admin.maps.status', $map)); ?>" class="inline">
                            <?php echo csrf_field(); ?> <?php echo method_field('patch'); ?>
                            <input type="hidden" name="status" value="<?php echo e($map->isPublished() ? 'draft' : 'published'); ?>">
                            <button type="submit"><?php echo e($map->isPublished() ? 'Unpublish' : 'Publish'); ?></button>
                        </form>
                        <form method="post" action="<?php echo e(route('admin.maps.destroy', $map)); ?>" class="inline" onsubmit="return confirm('Delete <?php echo e(addslashes($map->name)); ?> and all its signs and advertisements?')">
                            <?php echo csrf_field(); ?> <?php echo method_field('delete'); ?>
                            <button class="danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Maps'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Codes\KUET PROJECTS\3 2 SyStem\Integrated Smart Mall\map-service\resources\views/admin/maps/index.blade.php ENDPATH**/ ?>