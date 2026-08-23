<?php

use Turso\Driver\Laravel\Macros\VectorMacro;

// The validators are exercised directly rather than through the macros, so
// these tests need no database connection and run everywhere.

test('it accepts ordinary identifiers.', function () {
    expect(VectorMacro::assertIdentifier('movies_plot_embedding_idx', 'index name'))
        ->toBe('movies_plot_embedding_idx');

    expect(VectorMacro::quoteIdentifier('plot_embedding', 'column name'))
        ->toBe('"plot_embedding"');
})->group('VectorMacroTest', 'UnitTest');

test('it rejects an identifier that closes the statement.', function () {
    VectorMacro::assertIdentifier("idx') --", 'index name');
})->throws(InvalidArgumentException::class)->group('VectorMacroTest', 'UnitTest');

test('it rejects an identifier containing a quote.', function () {
    VectorMacro::assertIdentifier('idx"name', 'index name');
})->throws(InvalidArgumentException::class)->group('VectorMacroTest', 'UnitTest');

test('it rejects an identifier starting with a digit.', function () {
    VectorMacro::assertIdentifier('1idx', 'index name');
})->throws(InvalidArgumentException::class)->group('VectorMacroTest', 'UnitTest');

test('it builds a vector literal from numbers.', function () {
    expect(VectorMacro::vectorLiteral([1, 2, 3]))->toBe('[1.0,2.0,3.0]');
    expect(VectorMacro::vectorLiteral(['1.5', -2]))->toBe('[1.5,-2.0]');
})->group('VectorMacroTest', 'UnitTest');

test('it rejects a non-numeric vector component.', function () {
    // A component that would otherwise close the literal and the enclosing
    // vector_top_k() call.
    VectorMacro::vectorLiteral(["1]', 3) --"]);
})->throws(InvalidArgumentException::class)->group('VectorMacroTest', 'UnitTest');

test('it rejects an empty vector.', function () {
    VectorMacro::vectorLiteral([]);
})->throws(InvalidArgumentException::class)->group('VectorMacroTest', 'UnitTest');

test('it rejects a non-finite vector component.', function () {
    VectorMacro::vectorLiteral([1, INF]);
})->throws(InvalidArgumentException::class)->group('VectorMacroTest', 'UnitTest');

test('it accepts a positive integer limit.', function () {
    expect(VectorMacro::assertLimit(5))->toBe(5);
    expect(VectorMacro::assertLimit('10'))->toBe(10);
    expect(VectorMacro::assertLimit(10.0))->toBe(10);
})->group('VectorMacroTest', 'UnitTest');

test('it rejects a non-integer limit.', function () {
    VectorMacro::assertLimit('10) --');
})->throws(InvalidArgumentException::class)->group('VectorMacroTest', 'UnitTest');

test('it rejects a fractional limit.', function () {
    VectorMacro::assertLimit(2.5);
})->throws(InvalidArgumentException::class)->group('VectorMacroTest', 'UnitTest');

test('it rejects a limit below one.', function () {
    VectorMacro::assertLimit(0);
})->throws(InvalidArgumentException::class)->group('VectorMacroTest', 'UnitTest');
