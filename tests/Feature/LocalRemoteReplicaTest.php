<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Turso\Driver\Laravel\Tests\Fixtures\Models\Project;

beforeEach(function () {
    clearDirectory();
    sleep(2);
    DB::setDefaultConnection('otherdb2');
});

test('it can connect to a embedded replica', function () {
    DB::setDefaultConnection('otherdb2');
    $mode = DB::connection('otherdb2')->getConnectionMode();
    expect($mode)->toBe('remote_replica');
})->group('LocalRemoteReplicaTest', 'FeatureTest');

test('it can get all rows from the projects table through the embedded replica', function () {
    DB::setDefaultConnection('otherdb2');
    Schema::dropAllTables();
    migrateTables('projects');

    $this->project1 = Project::make()->setConnection('otherdb2')->factory()->create();
    $this->project2 = Project::make()->setConnection('otherdb2')->factory()->create();
    $this->project3 = Project::make()->setConnection('otherdb2')->factory()->create();
    $projects = DB::connection('otherdb2')->table('projects')->get();
    expect($projects->count())->toBe(3);
    clearDirectory();
})->group('LocalRemoteReplicaTest', 'FeatureTest');
