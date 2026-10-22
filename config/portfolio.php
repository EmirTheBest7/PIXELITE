<?php
// Portfolio data. The page /{lang}/portfolio is reachable but unlinked, noindex, and not in the sitemap.
// Replace the placeholders below with real projects, then link the page from the nav/footer yourself.
// Every text is a per-language map (en, cs, ru, uk); localized() falls back to English, then to any filled language, so a missing language never renders blank.
// Set 'placeholder' => false (or delete the key) on real entries; return [] to show the empty state.
return [
    [
        'placeholder' => true,
        'title' => ['en' => 'Project name', 'cs' => 'Název projektu', 'ru' => 'Название проекта', 'uk' => 'Назва проєкту'],
        'category' => ['en' => 'Website', 'cs' => 'Web', 'ru' => 'Сайт', 'uk' => 'Сайт'],
        'summary' => ['en' => 'One or two sentences about the project and its result.', 'cs' => 'Jedna dvě věty o projektu a jeho výsledku.', 'ru' => 'Одно-два предложения о проекте и его результате.', 'uk' => 'Одне-два речення про проєкт і його результат.'],
        'url' => null,
    ],
    [
        'placeholder' => true,
        'title' => ['en' => 'Project name', 'cs' => 'Název projektu', 'ru' => 'Название проекта', 'uk' => 'Назва проєкту'],
        'category' => ['en' => 'Web application', 'cs' => 'Webová aplikace', 'ru' => 'Веб-приложение', 'uk' => 'Вебзастосунок'],
        'summary' => ['en' => 'One or two sentences about the project and its result.', 'cs' => 'Jedna dvě věty o projektu a jeho výsledku.', 'ru' => 'Одно-два предложения о проекте и его результате.', 'uk' => 'Одне-два речення про проєкт і його результат.'],
        'url' => null,
    ],
];
