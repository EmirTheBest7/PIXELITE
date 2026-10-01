<?php
declare(strict_types=1);

namespace Pixelite;

/** Allowed values for the select fields. Labels live in lang/*.php under order.options.* */
final class OrderOptions
{
    public const PROJECT_TYPES = ['website', 'landing', 'webapp', 'software', 'ai', 'redesign', 'other'];
    public const BUDGETS = ['under1k', '1k-3k', '3k-5k', '5k-plus', 'unsure'];
    public const TIMEFRAMES = ['asap', '1-3m', '3m-plus', 'flexible'];
}
