<?php
// Usage: docker compose run --rm app php bin/hash-password.php   (prompts; password is not echoed)
//        or pass the password as the first argument (visible in shell history – prefer the prompt).
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$pw = $argv[1] ?? null;
if ($pw === null) {
    fwrite(STDERR, 'Password: ');
    system('stty -echo 2>/dev/null');
    $pw = trim((string) fgets(STDIN));
    system('stty echo 2>/dev/null');
    fwrite(STDERR, "\n");
}
if (strlen($pw) < 12) {
    fwrite(STDERR, "Use at least 12 characters.\n");
    exit(1);
}
echo "ADMIN_PASSWORD_HASH='" . password_hash($pw, PASSWORD_DEFAULT) . "'\n";
