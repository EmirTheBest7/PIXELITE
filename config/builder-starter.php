<?php
declare(strict_types=1);

/**
 * Starter page of a new public builder workspace. Bootstrap 5 markup with neutral placeholder text (no invented company facts, names, prices or numbers),
 * local placeholder pictures only. It is sanitised like anything else the visitor builds (tests/editor_public.php checks that nothing is stripped).
 */
return [
    'title' => 'My first page',
    'html' => '<header data-name="navigation"><nav class="navbar navbar-expand navbar-light bg-light border-bottom"><div class="container flex-wrap"><a class="navbar-brand fw-semibold" href="#">Your brand</a><ul class="navbar-nav ms-auto flex-row gap-3"><li class="nav-item"><a class="nav-link active" href="#">Home</a></li><li class="nav-item"><a class="nav-link" href="#">About</a></li><li class="nav-item"><a class="nav-link" href="#">Contact</a></li></ul></div></nav></header>'
        . '<section data-name="hero" class="py-5"><div class="container"><div class="row align-items-center g-5"><div class="col-lg-6"><h1 class="display-5 fw-bold">Headline goes here</h1><p class="lead text-secondary">Describe what you offer in one or two sentences. Click any text to change it, or add sections from the panel on the left.</p><a class="btn btn-primary btn-lg" href="#">Primary action</a></div><div class="col-lg-6"><img class="img-fluid rounded" src="/assets/editor/placeholders/image.svg" alt="Describe the picture"></div></div></div></section>'
        . '<section data-name="features" class="py-5 bg-light"><div class="container"><div class="row g-4"><div class="col-md-4"><h2 class="h5">First point</h2><p class="text-secondary mb-0">Short description of the first point.</p></div><div class="col-md-4"><h2 class="h5">Second point</h2><p class="text-secondary mb-0">Short description of the second point.</p></div><div class="col-md-4"><h2 class="h5">Third point</h2><p class="text-secondary mb-0">Short description of the third point.</p></div></div></div></section>'
        . '<footer data-name="footer" class="py-4 text-center text-secondary"><div class="container"><p class="small mb-0">© Your name</p></div></footer>',
    'css' => '',
];
