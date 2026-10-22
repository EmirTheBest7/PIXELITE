<h1 class="admin__title"><?= isset($message) ? 'Request not completed' : 'Not found' ?></h1>
<?php if (isset($message)): ?><div class="form__alert" role="alert"><?= e($message) ?></div><?php endif ?>
<p><a class="admin__back" href="/admin">← Admin home</a></p>
