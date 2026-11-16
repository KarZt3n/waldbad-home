<?php

// Schreibt die Zugangsdaten aus DATABASE_URL in eine MySQL-Optionsdatei (Argument 1) und gibt den
// Datenbanknamen aus. So landet das Passwort nicht in der Prozessliste von mysqldump/mysql.
// Aufruf aus einem Release-Verzeichnis: php8.4 deploy/prod/mysql-options.php <optionsdatei>

require 'vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv('.env');
$url = parse_url((string) ($_SERVER['DATABASE_URL'] ?? ''));
if ($url === false || !isset($url['host'], $url['user'], $url['path']) || !isset($argv[1])) {
    fwrite(STDERR, "DATABASE_URL ist ungültig oder die Optionsdatei fehlt.\n");
    exit(1);
}
$quote = static fn (string $value): string => '"'.addcslashes($value, '\\"').'"';
file_put_contents($argv[1], sprintf(
    "[client]\nhost=%s\nport=%d\nuser=%s\npassword=%s\n",
    $quote($url['host']),
    $url['port'] ?? 3306,
    $quote(rawurldecode($url['user'])),
    $quote(rawurldecode($url['pass'] ?? '')),
));
echo rawurldecode(ltrim($url['path'], '/'));
