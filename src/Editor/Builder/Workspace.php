<?php
declare(strict_types=1);

namespace Pixelite\Editor\Builder;

/**
 * EXPERIMENTAL site builder – what a builder request works on: the storage key, where its API and images are served, and its quotas.
 * Built only by the server after authentication (a customer's project, or an anonymous visitor's temporary workspace); nothing in it comes from the browser.
 */
final class Workspace
{
    /** @param array{pages:int,content:int,media_files:int,media_bytes:int} $limits */
    public function __construct(
        public readonly string $uid,          // storage key (project uid, or w<32 hex> for a temporary workspace)
        public readonly string $api,          // URL root of the builder API, no trailing slash
        public readonly string $mediaPrefix,  // URL prefix of this workspace's images: <prefix>/m<16 hex>
        public readonly string $owner,        // rate-limit owner key
        public readonly string $label,        // name shown in the editor
        public readonly bool $anonymous,
        public readonly array $limits,
    ) {}

    /** Quotas of a customer's project (as before the public builder existed). */
    public static function projectLimits(): array
    {
        return ['pages' => SiteStore::MAX_PAGES, 'content' => 0, 'media_files' => \Pixelite\Editor\Media::MAX_FILES, 'media_bytes' => \Pixelite\Editor\Media::MAX_TOTAL_BYTES];   // content 0 = unlimited (per-page caps still apply)
    }
}
