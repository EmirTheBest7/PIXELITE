<?php
declare(strict_types=1);

// Deletes expired TEMPORARY public-builder workspaces (24 h without activity) and their images. Only directories under storage/builder/ whose name is a workspace key
// are considered; customer projects (storage/projects) are never touched. The site also runs this opportunistically (hosting without cron); use this script for a cron job.
//   php bin/cleanup-builder.php [--dry-run]
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../src/bootstrap.php';

use Pixelite\Editor\Builder\Anon\AnonWorkspaces;

if (in_array('--dry-run', $argv, true)) {
    $n = 0;
    foreach (AnonWorkspaces::all() as $d) {
        $last = (int) (@filemtime($d . '/.activity') ?: @filemtime($d));
        if (time() - $last > AnonWorkspaces::TTL) { $n++; echo "would remove " . basename($d) . "\n"; }
    }
    echo "$n expired of " . count(AnonWorkspaces::all()) . " workspaces\n";
    exit(0);
}
$r = AnonWorkspaces::cleanup();
echo "checked {$r['checked']}, removed {$r['removed']}\n";
