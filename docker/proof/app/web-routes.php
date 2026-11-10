<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/proof/health', function () {
    return response()->json([
        'sapi' => PHP_SAPI,
        'extension' => extension_loaded('libsql_php') || extension_loaded('libsql'),
        'connection_mode' => DB::connection()->getConnectionMode(),
        'rows' => DB::table('proof_notes')->count(),
    ]);
});

Route::get('/proof/remote', function () {
    $original = config('database.connections.libsql');

    config()->set('database.connections.libsql', array_merge($original, [
        'database' => null,
        'url' => 'http://sqld:8080',
        'authToken' => 'proof-token',
    ]));
    DB::purge('libsql');

    try {
        return response()->json([
            'mode' => DB::connection()->getConnectionMode(),
            'rows' => DB::table('proof_notes')->count(),
        ]);
    } finally {
        config()->set('database.connections.libsql', $original);
        DB::purge('libsql');
    }
});

Route::get('/proof/throw', function () {
    DB::table('proof_notes')->insert(['name' => 'http-throw-a', 'email' => 'http-throw@example.com']);
    DB::table('proof_notes')->insert(['name' => 'http-throw-b', 'email' => 'http-throw@example.com']);

    return response()->json(['unexpected' => 'no exception was thrown'], 200);
});

Route::get('/', fn () => response()->json([
    'ok' => true,
    'proof_routes' => ['/proof/health', '/proof/remote', '/proof/throw'],
]));
