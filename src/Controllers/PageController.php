<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\Paths;
use Pixelite\Request;
use Pixelite\Response;
use Pixelite\TemplateCatalog;

final class PageController extends BaseController
{
    public function home(): Response
    {
        return $this->page('home', '');
    }

    public function pricing(): Response
    {
        return $this->page('pricing', 'pricing');
    }

    public function process(): Response
    {
        return $this->page('process', 'process');
    }

    /** ?template=<slug> (from a design page) is validated against the catalog; the view only ever sees trusted catalog data, never the raw request value. */
    public function contact(Request $r): Response
    {
        $entry = TemplateCatalog::find($r->query('template'));
        return $this->page('contact', 'contact', ['design' => $entry !== null ? TemplateCatalog::localized($entry) : null]);
    }

    public function careers(): Response
    {
        return $this->page('careers', 'careers', ['vacancies' => \Pixelite\Careers::all(true)]);
    }

    public function team(): Response
    {
        return $this->page('team', 'team', ['members' => require Paths::root('config/team.php')]);
    }

    public function cookies(Request $r): Response
    {
        return $this->page('cookies', 'cookies', ['sessionCookie' => \Pixelite\Session::cookieName($r)]);
    }

    public function terms(): Response
    {
        return $this->page('terms', 'terms');
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
