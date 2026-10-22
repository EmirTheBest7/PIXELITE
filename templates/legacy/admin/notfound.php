<div class="row"><h1 class="row__title"><?= isset($message) ? 'Request not completed' : 'Not found' ?></h1></div>
<?php if (isset($message)): ?><div class="form__alert" role="alert"><?= e($message) ?></div><?php endif ?>
<p class="text-center"><a href="/admin">← Admin home</a></p>
