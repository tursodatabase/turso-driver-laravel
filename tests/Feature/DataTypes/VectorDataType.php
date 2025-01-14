<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create("movies", function ($table) {
        $table->id();
        $table->string("title");
        $table->string("genre");
        $table->integer("release_year");
        $table->vector("plot_embedding", 5); // 5-dimensional vector
        $table->timestamps();
    });

    Schema::table("movies", function ($table) {
        $table->vectorIndex("plot_embedding", "movies_plot_embedding_idx");
    });
});

afterEach(function () {
    Schema::dropAllTables();
});

test('it can insert a new vector data', function () {
    $embedding = [0.1, 0.2, 0.3, 0.4, 0.5];

    DB::table("movies")->insert([
        "title" => "The Matrix",
        "genre" => "Action",
        "release_year" => 1999,
        "plot_embedding" => $embedding,
    ]);

    $movie = DB::table("movies")->first();

    expect($movie->plot_embedding)->toBe($embedding);
})->group('VectorDataType', 'FeatureTest');

test('it can find nearest vector data', function () {

    DB::table("movies")->insert([
        [
            'title' => 'The Matrix',
            'genre' => 'Action',
            'release_year' => 1999,
            'plot_embedding' => [0.1, 0.2, 0.3, 0.4, 0.5],
        ],
        [
            'title' => 'Inception',
            'genre' => 'Sci-Fi',
            'release_year' => 2010,
            'plot_embedding' => [0.15, 0.25, 0.35, 0.45, 0.55],
        ],
        [
            'title' => 'Interstellar',
            'genre' => 'Sci-Fi',
            'release_year' => 2014,
            'plot_embedding' => [0.2, 0.3, 0.4, 0.5, 0.6],
        ],
    ]);

    $queryVector = [0.15, 0.25, 0.35, 0.45, 0.55];
    $result = DB::table("movies")
        ->nearest('movies_plot_embedding_idx', $queryVector, 5)
        ->get();

    expect($result->count())->toBe(3);
})->group('VectorDataType', 'FeatureTest');
