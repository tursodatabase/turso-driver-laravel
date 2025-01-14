<?php

use Illuminate\Support\Facades\DB;

test('it can enable query logging feature', function () {
    DB::connection('libsql')->enableQueryLog();

    expect(DB::connection('libsql')->logging())->toBeTrue();
})->group('LibSQLConnectionTest', 'UnitTest');

test('it can disable query logging feature', function () {
    DB::connection('libsql')->disableQueryLog();

    expect(DB::connection('libsql')->logging())->toBeFalse();
})->group('LibSQLConnectionTest', 'UnitTest');

test('it can get the query log', function () {
    DB::connection('libsql')->enableQueryLog();

    $log = DB::connection('libsql')->getQueryLog();

    expect($log)->toBeArray()
        ->and($log)->toHaveCount(0);
})->group('LibSQLConnectionTest', 'UnitTest');

test('it can flush the query log', function () {
    DB::connection('libsql')->enableQueryLog();

    DB::connection('libsql')->flushQueryLog();

    $log = DB::connection('libsql')->getQueryLog();

    expect($log)->toBeArray()
        ->and($log)->toHaveCount(0);
})->group('LibSQLConnectionTest', 'UnitTest');
