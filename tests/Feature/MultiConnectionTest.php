<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Turso\Driver\Laravel\Tests\Fixtures\Models\Project;

beforeEach(function () {
    DB::setDefaultConnection('otherdb');
    Schema::dropAllTables();
    migrateTables('projects');

    $this->project1 = Project::make()->setConnection('otherdb')->factory()->create();
    $this->project2 = Project::make()->setConnection('otherdb')->factory()->create();
    $this->project3 = Project::make()->setConnection('otherdb')->factory()->create();
});

afterEach(function () {
    DB::setDefaultConnection('otherdb');
    Schema::dropAllTables();
});

test('it can connect to a in-memory database', function () {
    $mode = DB::connection('libsql')->getConnectionMode();
    expect($mode)->toBe('memory');
})->group('MultiConnectionsTest', 'FeatureTest');

test('it can connect to a remote database', function () {
    $mode = DB::connection('otherdb')->getConnectionMode();
    expect($mode)->toBe('remote');
})->group('MultiConnectionsTest', 'FeatureTest');

test('each connection has its own libsql client instance', function () {
    $client1 = DB::connection('libsql')->getPdo(); // In Memory Connection
    $client2 = DB::connection('otherdb')->getPdo(); // Remote Connection

    expect($client1)->not->toBe($client2);
})->group('MultiConnectionsTest', 'FeatureTest');

test('it can get all rows from the projects table through the remote connection', function () {
    $projects = DB::connection('otherdb')->table('projects')->get();

    expect($projects)->toHaveCount(3)
        ->and($projects[0]->name)->toEqual($this->project1->name)
        ->and($projects[1]->name)->toEqual($this->project2->name)
        ->and($projects[2]->name)->toEqual($this->project3->name);
})->group('MultiConnectionsTest', 'FeatureTest');
