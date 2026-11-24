<?php $__env->startSection('content'); ?>
<h1>API keys</h1>
<p class="lead">Other apps, such as the Smart Mall app, call <code><?php echo e(url('/api/v1')); ?></code> with one of these keys in the header <code>Authorization: Bearer &lt;key&gt;</code>. Only a fingerprint of each key is stored, so a key can be copied only right after it is created.</p>

<?php if(session('new_key')): ?>
    <div class="card" style="border-color:var(--accent)">
        <h2>Your new key</h2>
        <span class="key"><?php echo e(session('new_key')); ?></span>
        <p class="muted">Put it in the other app's <code>.env</code>, for example <code>MAP_SERVICE_KEY=<?php echo e(session('new_key')); ?></code>. It will not be shown again.</p>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Create a key</h2>
    <form method="post" action="<?php echo e(route('admin.api-keys.store')); ?>" class="grid">
        <?php echo csrf_field(); ?>
        <label>Name (who uses it) <input type="text" name="name" value="<?php echo e(old('name', 'Smart Mall app')); ?>" maxlength="80" required></label>
        <label>Expires after (days, empty = never) <input type="number" name="expires_in_days" value="<?php echo e(old('expires_in_days')); ?>" min="1" max="3650"></label>
        <div>
            <?php $__currentLoopData = $abilities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ability => $description): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label class="check" title="<?php echo e($description); ?>"><input type="checkbox" name="abilities[]" value="<?php echo e($ability); ?>" <?php if(in_array($ability, old('abilities', ['maps:read']), true)): echo 'checked'; endif; ?>> <code><?php echo e($ability); ?></code></label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <div><button class="primary" type="submit">Create key</button></div>
    </form>
    <p class="muted" style="margin-bottom:0"><?php $__currentLoopData = $abilities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ability => $description): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><code><?php echo e($ability); ?></code> <?php echo e($description); ?>. <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></p>
</div>

<div class="card">
    <h2>Keys</h2>
    <?php if($keys->isEmpty()): ?>
        <p class="muted">None yet.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Name</th><th>Status</th><th>Abilities</th><th></th></tr></thead>
            <tbody>
            <?php $__currentLoopData = $keys; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td>
                        <div><?php echo e($key->name); ?></div>
                        <div class="muted" style="font-size:12px"><code><?php echo e($key->prefix); ?>…</code> · created <?php echo e($key->created_at->format('Y-m-d')); ?> · last used <?php echo e($key->last_used_at?->diffForHumans() ?? 'never'); ?></div>
                    </td>
                    <td>
                        <?php if($key->revoked_at): ?>
                            <span class="badge bad">revoked</span>
                        <?php elseif($key->expires_at && $key->expires_at->isPast()): ?>
                            <span class="badge bad">expired</span>
                        <?php else: ?>
                            <span class="badge ok">active</span><?php if($key->expires_at): ?><div class="muted" style="font-size:12px">until <?php echo e($key->expires_at->format('Y-m-d')); ?></div><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?php $__currentLoopData = $key->abilities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ability): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><code><?php echo e($ability); ?></code> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></td>
                    <td class="actions">
                        <?php if(! $key->revoked_at): ?>
                            <form method="post" action="<?php echo e(route('admin.api-keys.revoke', $key)); ?>" class="inline" onsubmit="return confirm('Revoke <?php echo e(addslashes($key->name)); ?>? Apps using it stop working.')">
                                <?php echo csrf_field(); ?>
                                <button class="danger" type="submit">Revoke</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'API keys'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Codes\KUET PROJECTS\3 2 SyStem\Integrated Smart Mall\map-service\resources\views/admin/api-keys/index.blade.php ENDPATH**/ ?>