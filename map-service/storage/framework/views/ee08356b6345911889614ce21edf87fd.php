<?php $__env->startSection('content'); ?>
<div class="card" style="max-width:420px; margin:40px auto">
    <h1>ISM Map Service</h1>
    <p class="lead">Sign in to import maps, place shop signs and advertisements, and manage API keys.</p>
    <form method="post" action="<?php echo e(url('/login')); ?>" style="display:flex; flex-direction:column; gap:12px">
        <?php echo csrf_field(); ?>
        <label>Email <input type="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus autocomplete="username"></label>
        <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
        <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
        <button class="primary" type="submit">Sign in</button>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Sign in'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Codes\KUET PROJECTS\3 2 SyStem\Integrated Smart Mall\map-service\resources\views/login.blade.php ENDPATH**/ ?>