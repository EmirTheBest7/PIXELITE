<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\Paths;
use Pixelite\Response;

final class PageController extends BaseController
{
    public function home(): Response
    {
        return $this->page('home', '');
    }

    public function contact(): Response
    {
        return $this->page('contact', 'contact');
    }

    public function privacy(): Response
    {
        return $this->page('privacy', 'privacy');
    }

    /** Reachable by URL but deliberately unlinked, noindex and absent from the sitemap until real projects exist. */
    public function portfolio(): Response
    {
        $projects = require Paths::root('config/portfolio.php');
        return $this->page('portfolio', 'portfolio', ['projects' => $projects], 200, ['robots' => 'noindex,nofollow']);
    }
}
