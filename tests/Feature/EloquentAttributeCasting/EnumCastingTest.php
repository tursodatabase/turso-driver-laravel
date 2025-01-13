<?php

use Illuminate\Support\Facades\Schema;
use Turso\Driver\Laravel\Tests\Feature\EloquentAttributeCasting\CastingModels\EnumCastingModel;
use Turso\Driver\Laravel\Tests\Feature\EloquentAttributeCasting\Enums\Status;

beforeEach(function () {
    Schema::create('enum_casting_table', function ($table) {
        $table->id();
        $table->tinyInteger('status');
    });
});

afterEach(function () {
    Schema::dropIfExists('enum_casting_table');
});

test('it can insert a new record using Eloquent ORM', function () {
    $status = Status::Approved;

    EnumCastingModel::create([
        'status' => $status->value,
    ]);

    $result = EnumCastingModel::first();

    expect($result->id)->toBe(1)
        ->and(gettype($result->status))->toBe('object')
        ->and(get_class($result->status))->toBe(Status::class)
        ->and($result->status)->toBe($status);
})->group('EnumCastingTest', 'EloquentAttributeCastings', 'FeatureTest');

test('it can update an existing record using Eloquent ORM', function () {
    $status = Status::Approved;

    EnumCastingModel::create([
        'status' => $status->value,
    ]);

    $newStatus = Status::Rejected;

    EnumCastingModel::first()->update([
        'status' => $newStatus->value,
    ]);

    $result = EnumCastingModel::first();

    expect($result->id)->toBe(1)
        ->and(gettype($result->status))->toBe('object')
        ->and(get_class($result->status))->toBe(Status::class)
        ->and($result->status)->toBe($newStatus);
})->group('EnumCastingTest', 'EloquentAttributeCastings', 'FeatureTest');

test('it can find the saved record using Eloquent ORM', function () {
    $status = Status::Approved;

    EnumCastingModel::create([
        'status' => $status,
    ]);

    $result = EnumCastingModel::where('status', $status->value)->first();

    expect($result->id)->toBe(1)
        ->and(gettype($result->status))->toBe('object')
        ->and(get_class($result->status))->toBe(Status::class)
        ->and($result->status)->toBe($status);
})->group('EnumCastingTest', 'EloquentAttributeCastings', 'FeatureTest');
