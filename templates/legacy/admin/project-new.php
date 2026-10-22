<?php /** @var array $old @var list<string> $errors @var string $csrf */ ?>
<a class="admin__back" href="/admin/projects">← All projects</a>
<div class="admin__card">
  <h1 class="row__title admin__title">New project</h1>
<?php foreach ($errors as $er): ?><div class="form__alert" role="alert"><?= e($er) ?></div><?php endforeach ?>
  <form method="post" action="/admin/projects">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label for="pn">Project name</label>
    <input class="form__field form__text" id="pn" name="name" required maxlength="<?= \Pixelite\Projects::MAX_NAME ?>" value="<?= e($old['name']) ?>">
    <label for="po">Related order number (optional)</label>
    <input class="form__field form__text" id="po" name="order_id" inputmode="numeric" pattern="[0-9]*" value="<?= e($old['order_id']) ?>">
    <label for="pc">Customer account email (optional – the customer then sees this project in their account)</label>
    <p class="auth-field__hint">Accounts are not e-mail verified. Anyone can register any address, so check that the account really belongs to your client (see its registration date on the Customers page) before linking an order or project: the customer gets the preview link and order summary.</p>
    <input class="form__field form__text" id="pc" name="customer_email" type="email" value="<?= e($old['customer_email']) ?>">
    <label for="pt">Internal notes (never shown to the customer)</label>
    <textarea class="form__field form__textarea" id="pt" name="notes" maxlength="<?= \Pixelite\Projects::MAX_NOTES ?>"><?= e($old['notes']) ?></textarea>
    <div class="admin__actions"><button class="btn btn--auto" type="submit">Create project</button></div>
  </form>
</div>
