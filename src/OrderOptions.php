<?php
declare(strict_types=1);

namespace Pixelite;

/** Allowed values for the select fields. Labels live in lang/*.php under order.options.* */
final class OrderOptions
{
    public const PROJECT_TYPES = ['website', 'landing', 'webapp', 'software', 'ai', 'redesign', 'other'];
    /** Pricing packages shown on the landing page (stable ids; labels come from lang services.items). Separate from PROJECT_TYPES. */
    public const PACKAGES = ['template', 'basic', 'custom'];
    public const BUDGETS = ['under10k', '10k-30k', '30k-65k', '65k-100k', '100k-plus', 'unsure'];   // CZK ranges (labels in lang), aligned with the public prices and the credit threshold
    public const TIMEFRAMES = ['asap', '1-3m', '3m-plus', 'flexible'];
}
