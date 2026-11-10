<?php

declare(strict_types=1);

// Host-side Hrana v2/pipeline probe — independent of the app's network
// state (the outage window only cuts the app ↔ sqld link). Used by
// proof.sh to assert the SQld side of the story from OUTSIDE the outage.
//
// Usage: php docker/proof/host-hrana-check.php <table>
// Prints "no-such-table" when the table does not (yet) exist on the server,
// otherwise the integer count(*) of the table. Exit 1 on any other outcome.

$table = $argv[1] ?? '';
if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
    fwrite(STDERR, "refusing unsafe table name\n");
    exit(1);
}

$port = getenv('SQLD_PORT') ?: '8081';

$payload = json_encode(['requests' => [
    ['type' => 'execute', 'stmt' => ['sql' => "SELECT count(*) FROM {$table}"]],
    ['type' => 'close'],
]]);

$context = stream_context_create(['http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/json\r\n",
    'content' => $payload,
    'timeout' => 5,
    'ignore_errors' => true,
]]);

$raw = @file_get_contents("http://127.0.0.1:{$port}/v2/pipeline", false, $context);
if ($raw === false) {
    fwrite(STDERR, "sqld HTTP request failed (is sqld up on 127.0.0.1:{$port}?)\n");
    exit(1);
}

$decoded = json_decode($raw, true);

// "no such table" from the wire means the table does not exist yet — a
// valid state for the journal table before the first sync.
$message = $decoded['results'][0]['error']['message'] ?? null;
if ($message !== null && str_contains($message, 'no such table')) {
    echo "no-such-table\n";
    exit(0);
}

$value = $decoded['results'][0]['response']['result']['rows'][0][0]['value'] ?? null;
if ($value === null || ! is_numeric($value)) {
    fwrite(STDERR, "unexpected Hrana response: {$raw}\n");
    exit(1);
}

echo ((int) $value) . "\n";
exit(0);
