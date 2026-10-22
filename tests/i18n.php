<?php
declare(strict_types=1);

// Included at the end of tests/features.php (shares check(), $page, $rq, $H, $guest …; runs once per design). Localization regression tests:
// they assert what a visitor actually sees in Russian and Ukrainian, not merely that dictionary keys exist.

use Pixelite\{Careers, Env, I18n};

echo "\nlocalization\n";
$vis = fn(string $html): string => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) preg_replace('#<(script|style|svg|template)\b.*?</\1>#s', '', $html)), ENT_QUOTES, 'UTF-8')));
$ru = I18n::load('ru');
$uk = I18n::load('uk');
$flatRu = I18n::flatten($ru);
$flatUk = I18n::flatten($uk);

/* ---------------------------------------------------------------- portfolio: no blank content in any language */
$pf = require \Pixelite\Paths::root('config/portfolio.php');
check('portfolio config: every entry has a non-empty title, category and summary in all four languages', (function () use ($pf) {
    foreach ($pf as $p) { foreach (['title', 'category', 'summary'] as $f) { foreach (I18n::LOCALES as $l) { if (!isset($p[$f][$l]) || trim((string) $p[$f][$l]) === '') { return false; } } } }
    return $pf !== [];
})());
foreach (['ru' => ['Название проекта', 'Веб-приложение'], 'uk' => ['Назва проєкту', 'Вебзастосунок']] as $lg => [$title, $cat]) {
    $b = $page("/$lg/portfolio");
    check("portfolio ($lg): the placeholder projects show a localized title, category and summary instead of blank cards", str_contains($b, $title) && str_contains($b, $cat) && !preg_match('#<(?:h2|h3) class="article-pre__title"></(?:h2|h3)>#', $b) && !str_contains($b, '<span class="article-pre__cat"></span>') && !str_contains($b, '<p class="portfolio__summary"></p>'));
}
check('localized(): active language, then English, then any filled language; never an empty string while text exists', localized(['en' => 'E', 'ru' => 'R'], 'ru') === 'R' && localized(['en' => 'E'], 'uk') === 'E' && localized(['en' => '', 'cs' => 'C'], 'uk') === 'C' && localized(['en' => 'E', 'uk' => '  '], 'uk') === 'E' && localized([], 'uk') === '' && localized('plain') === 'plain');

/* ---------------------------------------------------------------- careers: whole-language text or an explicit English fallback */
Env::set('CONTACT_EMAIL', 'hello@pixelite.test');
$vId = Careers::create(['titles' => ['en' => 'Frontend developer', 'ru' => 'Фронтенд-разработчик', 'uk' => 'Фронтенд-розробник'], 'descriptions' => ['en' => 'Build interfaces.', 'ru' => 'Создавать интерфейсы.']]);   // uk has a title but NO description
$wId = Careers::create(['titles' => ['en' => 'Designer'], 'descriptions' => ['en' => 'Design websites.']]);
Careers::setPublished($vId, true);
Careers::setPublished($wId, true);
$cr = $page('/ru/careers');
check('careers (ru): a vacancy translated into Russian is shown in Russian without any fallback note', str_contains($cr, 'Фронтенд-разработчик') && str_contains($cr, 'Создавать интерфейсы.') && !str_contains($cr, 'Frontend developer'));
check('careers (ru): a vacancy that exists only in English is shown in English, marked lang="en" and labelled as English-only', str_contains($cr, 'Design websites.') && str_contains($cr, 'lang="en"') && str_contains($cr, I18n::get('careers.english_only', [], 'ru')));
$cu = $page('/uk/careers');
check('careers (uk): a half-translated vacancy (title without description) falls back as a whole, never a Ukrainian title over English text', str_contains($cu, 'Frontend developer') && str_contains($cu, 'Build interfaces.') && !str_contains($cu, 'Фронтенд-розробник') && str_contains($cu, I18n::get('careers.english_only', [], 'uk')));
check('careers (en): English pages carry no fallback note', !str_contains($page('/en/careers'), I18n::get('careers.english_only', [], 'en')));
check('Careers::text() reports the language actually used', Careers::text(Careers::find($vId), 'ru')['lang'] === 'ru' && Careers::text(Careers::find($vId), 'uk')['fallback'] === true && Careers::text(Careers::find($wId), 'cs')['lang'] === 'en');
Careers::delete($vId);
Careers::delete($wId);
Env::set('CONTACT_EMAIL', '');

/* ---------------------------------------------------------------- language and design switches keep the query string */
$mkq = fn(string $path, array $q) => $H($rq('GET', $path, [], $q))->body;
$hrefs = function (string $html, string $attr): array { preg_match_all('#<a [^>]*' . $attr . '[^>]*>#', $html, $m); $o = []; foreach ($m[0] as $tag) { if (preg_match('#href="([^"]+)"#', $tag, $h)) { $o[] = html_entity_decode($h[1]); } } return $o; };
$cur = \Pixelite\Design::prefix();                                // '' for the default design, '/legacy' or '/new' for the other one
$other = \Pixelite\Design::prefix(\Pixelite\Design::alternate());
$b = $mkq('/en/templates', ['q' => 'lumora', 'category' => 'studio']);
$ls = $hrefs($b, 'hreflang=');
check('language switch keeps the gallery filters (/templates?q=…&category=… in every language)', in_array("$cur/ru/templates?q=lumora&category=studio", $ls, true) && in_array("$cur/uk/templates?q=lumora&category=studio", $ls, true) && in_array("$cur/cs/templates?q=lumora&category=studio", $ls, true), implode(' ', $ls));
check('design switch keeps language, page and query', in_array("$other/en/templates?q=lumora&category=studio", $hrefs($b, 'data-design-switch'), true), implode(' ', $hrefs($b, 'data-design-switch')));
$b = $mkq('/ru/contact', ['template' => 'lumora']);
check('contact?template=lumora: the language and design switches keep the chosen design', in_array("$cur/uk/contact?template=lumora", $hrefs($b, 'hreflang='), true) && in_array("$other/ru/contact?template=lumora", $hrefs($b, 'data-design-switch'), true));
$b = $mkq('/uk/order', ['package' => 'basic', 'next' => '/uk/account', 'junk' => '']);
check('order?package=…: kept; the one-language "next" and empty parameters are dropped', in_array("$cur/en/order?package=basic", $hrefs($b, 'hreflang='), true));
check('a page without a query gets clean switch links (no stray "?")', !preg_match('#(?:hreflang|data-design-switch)[^>]*href="[^"]*\?"#', $page('/ru/pricing')) && in_array("$cur/uk/pricing", $hrefs($page('/ru/pricing'), 'hreflang='), true));
check('canonical and hreflang alternates never carry the query string (SEO unchanged)', !preg_match('#<link rel="(?:canonical|alternate)"[^>]*\?#', $mkq('/en/templates', ['q' => 'lumora'])));

/* ---------------------------------------------------------------- Russian and Ukrainian pages contain no unexpected English interface text */
$allowedLatin = '/\b(?:Pixelite|Dreamers|Ad|Credits|cookie|Cookie|SEO|SaaS|CZK|USD|GDPR|Telegram|IČO|DIČ|DPH|ADR|Česká|obchodní|inspekce|Úřad|pro|ochranu|osobních|údajů|uoou|gov|cz|coi|reklamace|e-mail|Lumora|Asterform|Stillwave|Vantage|Emberveil|HTML|CSS|JavaScript|ES|modules|Lenis|Onest|Instrument|Serif|Inter|MIT|SIL|OFL|ICO|UI|AI|CRM|IP|EU|EEA|Founder|Emir|Aliev|Pixelite\.cz)\b/u';
$english = '/\b(?:the|and|your|with|for|you|our|are|please|from|this|that|have|not|will|sign|start|learn|more|read|contact us|home|menu|about)\b/i';
foreach (['ru', 'uk'] as $lg) {
    $bad = [];
    foreach (['', 'pricing', 'process', 'contact', 'privacy', 'cookies', 'terms', 'order', 'templates', 'templates/lumora', 'templates/asterform', 'templates/stillwave', 'templates/vantage', 'templates/emberveil', 'templates/azur', 'team', 'careers', 'login', 'register', 'portfolio', 'no-such-page'] as $pg) {
        $guest();
        $text = $vis($page("/$lg/$pg"));
        $text = preg_replace($allowedLatin, ' ', $text);
        if (preg_match_all($english, (string) $text, $m)) { $bad[] = "$pg:" . implode(',', array_unique($m[0])); }
    }
    check("$lg: no English interface text on any public page (brand names, legal references and technical identifiers excluded)", $bad === [], implode(' | ', $bad));
}
foreach (['ru', 'uk'] as $lg) {
    $o = $page("/$lg/order");
    $guest();
    $e = $H($rq('POST', "/$lg/order", ['_csrf' => '', 'name' => '', 'email' => 'x', 'description' => 'short']));
    $t = $vis($e->body);
    check("$lg: order form validation errors are shown in the language of the page", !preg_match($english, (string) preg_replace($allowedLatin, ' ', $t)) && (str_contains($t, I18n::get('order.errors.email_invalid', [], $lg)) || str_contains($t, I18n::get('order.form_errors.csrf', [], $lg)) || str_contains($t, I18n::get('order.form_errors.fix', [], $lg))));
}

/* ---------------------------------------------------------------- terminology and language quality (see docs/i18n/TERMINOLOGY.md) */
$joinRu = implode("\n", $flatRu);
$joinUk = implode("\n", $flatUk);
check('ru/uk: no awkward gendered "(а)" / "(ла)" constructions', !preg_match('/\((?:а|ла|ась|лась)\)/u', $joinRu . $joinUk));
check('ru: no Ukrainian-only letters; uk: no Russian-only letters (ы, э, ъ, ё)', !preg_match('/[іїєґ]/u', $joinRu) && !preg_match('/[ыэъё]/u', $joinUk));
check('uk: "проєкт" spelling, never "проект"; ru: "проект", never "проєкт"', !preg_match('/проект/ui', $joinUk) && !preg_match('/проєкт/ui', $joinRu));
check('uk: the account is always "обліковий запис" (never the Russian-style "акаунт/аккаунт")', !preg_match('/акаунт|аккаунт/ui', $joinUk) && str_contains($flatUk['account.title'], 'обліковий запис'));
check('ru: the account is "аккаунт" throughout (never "учётная запись" next to it)', !preg_match('/учётн|учетн/ui', $joinRu) && str_contains($flatRu['account.title'], 'аккаунт'));
check('ru/uk: a project request is a "заявка" (never "запрос/запит" for the order itself)', str_contains($flatRu['order.title'], 'Заявка на проект') && str_contains($flatUk['order.title'], 'Заявка на проєкт') && str_contains($flatRu['order.submit'], 'заявк') && str_contains($flatUk['order.submit'], 'заявк') && str_contains($flatRu['account.orders'], 'заявки') && str_contains($flatUk['account.orders'], 'заявки'));
check('ru/uk: designs, comparison, portfolio and starting-price terminology', $flatRu['nav.templates'] === 'Дизайны сайтов' && $flatUk['nav.templates'] === 'Дизайни сайтів' && $flatRu['pricing.compare_title'] === 'Сравнение пакетов' && $flatUk['pricing.compare_title'] === 'Порівняння пакетів' && $flatRu['portfolio.title'] === 'Наши проекты' && $flatUk['portfolio.title'] === 'Наші проєкти' && $flatRu['services.items.0.from'] === 'От' && $flatUk['services.items.0.from'] === 'Від');
check('ru/uk: the live preview is called an interactive demo and says the visitor can use the real website', str_contains($flatRu['templates.preview_heading'], 'демо') && str_contains($flatUk['templates.preview_heading'], 'демо') && str_contains($flatRu['templates.preview_hint'], 'работающий сайт') && str_contains($flatUk['templates.preview_hint'], 'робочий сайт'));
check('ru/uk: "low running costs" refers to maintaining the website', str_contains($flatRu['services.items.0.features.5'], 'обслуживание сайта') && str_contains($flatUk['services.items.0.features.5'], 'обслуговування сайту'));
check('ru/uk: contact CTA wording is a plain "contact form or button"', str_contains($flatRu['services.items.0.features.3'], 'форма') && str_contains($flatRu['services.items.0.features.3'], 'кнопка') && str_contains($flatUk['services.items.0.features.3'], 'форма') && str_contains($flatUk['services.items.0.features.3'], 'кнопка'));
check('ru/uk: "no project added to the account yet" reads naturally', $flatRu['account.projects_none'] === 'В вашем аккаунте пока нет ни одного проекта.' && $flatUk['account.projects_none'] === 'До вашого облікового запису ще не додано жодного проєкту.');
check('uk: the Asterform philosophy section is a two-column section (not "two-row"), and ru likewise', !str_contains($flatUk['templates.items.asterform.features.1'], 'дворядков') && str_contains($flatUk['templates.items.asterform.features.1'], 'у дві колонки') && str_contains($flatRu['templates.items.asterform.features.1'], 'двухколоночный раздел о философии'));
check('ru/uk: ukrainian copy is not a copy of the Russian copy (independently edited)', (function () use ($flatRu, $flatUk) { $same = 0; foreach ($flatRu as $k => $v) { if (is_string($v) && mb_strlen($v) > 25 && !str_ends_with($k, '.url') && ($flatUk[$k] ?? null) === $v) { $same++; } } return $same === 0; })(), '');
check('prices and budget ranges keep non-breaking spaces and the same amounts in every language', (function () { foreach (I18n::LOCALES as $l) { $d = I18n::load($l); $a = array_map(fn($i) => preg_replace('/\D/', '', $i['amount']), $d['services']['items']); if ($a !== ['2999', '32900', '64900']) { return false; } foreach ($d['order']['options']['budget'] as $k => $v) { if (preg_match('/\d \d{3}/', $v)) { return false; } } } return true; })());
check('legal placeholders stay visible in every language (no unfinished legal text presented as final)', (function () { $cnt = []; foreach (I18n::LOCALES as $l) { $d = I18n::flatten(I18n::load($l)); $n = 0; foreach ($d as $k => $v) { if (is_string($v) && str_starts_with($k, 'terms.sections') && preg_match('/^\[.+\]$/u', $v)) { $n++; } } $cnt[$l] = $n; } return count(array_unique($cnt)) === 1 && $cnt['en'] >= 8; })());
check('the structured data lists all four languages for the website', (function () use ($page) { return str_contains($page('/en/'), '"inLanguage":["en","cs","ru","uk"]'); })());
check('the mobile menu button label is translated (no hard-coded English "Menu")', I18n::get('nav.menu', [], 'ru') === 'Меню' && !str_contains($page('/ru/'), '>Menu<') && !str_contains($page('/uk/'), '>Menu<'));
check('the table-of-contents label is translated on legal pages', !str_contains($page('/ru/privacy'), 'On this page') && !str_contains($page('/uk/terms'), 'On this page') && !str_contains($page('/cs/cookies'), 'On this page'));

/* ---------------------------------------------------------------- signed-in account and error pages in ru/uk */
foreach (['ru', 'uk'] as $lg) {
    $cid = \Pixelite\Customers::create(['name' => 'Test ' . $lg, 'email' => "i18n-$lg@example.cz", 'company' => '', 'phone' => '', 'password' => 'i18n password 12345'], $lg);
    $cust($cid);
    $acc = $H($rq('GET', "/$lg/account"));
    $t = $vis($acc->body);
    check("account ($lg): empty states (no orders, no projects) are shown in the language of the page, with no English text", $acc->status === 200 && str_contains($t, I18n::get('account.orders_none', [], $lg)) && str_contains($t, I18n::get('account.projects_none', [], $lg)) && !preg_match($english, (string) preg_replace($allowedLatin, ' ', $t)));
    $guest();
    $nf = $H($rq('GET', "/$lg/no-such-page"));
    check("404 ($lg): localized title and text, <html lang> matches", $nf->status === 404 && str_contains($nf->body, I18n::get('errors.404_title', [], $lg)) && str_contains($nf->body, "<html lang=\"$lg\">"));
    I18n::set($lg);
    $e500 = \Pixelite\View::render('errors/500', ['meta' => ['page' => '500', 'path' => '', 'title' => (string) t('meta.500.title'), 'description' => '', 'robots' => 'noindex,nofollow'], 'locale' => $lg]);
    check("500 ($lg): the error page is localized", str_contains($e500, I18n::get('errors.500_title', [], $lg)) && str_contains($e500, I18n::get('errors.500_text', [], $lg)));
    I18n::set('en');
}
