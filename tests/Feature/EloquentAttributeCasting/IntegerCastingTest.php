<?php

use Illuminate\Support\Facades\Schema;
use Turso\Driver\Laravel\Tests\Feature\EloquentAttributeCasting\CastingModels\IntegerCastingModel;

beforeEach(function () {
    Schema::create('integer_casting_table', function ($table) {
        $table->id();
        $table->integer('amount');
    });
});

afterEach(function () {
    Schema::dropIfExists('integer_casting_table');
});

test('it can insert a new record using Eloquent ORM', function () {
    $amount = 100;

    IntegerCastingModel::create([
        'amount' => $amount,
    ]);

    $result = IntegerCastingModel::first();

    expect($result->id)->toBe(1)
        ->and(gettype($result->amount))->toBe('integer')
        ->and($result->amount)->toBe($amount);
})->group('IntegerCastingTest', 'EloquentAttributeCastings', 'FeatureTest');

test('it can update an existing record using Eloquent ORM', function () {
    $amount = 100;

    IntegerCastingModel::create([
        'amount' => $amount,
    ]);

    $newAmount = 200;

    IntegerCastingModel::first()->update([
        'amount' => $newAmount,
    ]);

    $result = IntegerCastingModel::first();

    expect($result->id)->toBe(1)
        ->and(gettype($result->amount))->toBe('integer')
        ->and($result->amount)->toBe($newAmount);
})->group('IntegerCastingTest', 'EloquentAttributeCastings', 'FeatureTest');

test('it can find a record using Eloquent ORM', function () {
    $amount = 100;

    IntegerCastingModel::create([
        'amount' => $amount,
    ]);

    $result = IntegerCastingModel::where('amount', $amount)->first();

    expect($result->id)->toBe(1)
        ->and(gettype($result->amount))->toBe('integer')
        ->and($result->amount)->toBe($amount);
})->group('IntegerCastingTest', 'EloquentAttributeCastings', 'FeatureTest');
