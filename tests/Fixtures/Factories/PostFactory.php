<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Turso\Driver\Laravel\Tests\Fixtures\Models\Post;
use Turso\Driver\Laravel\Tests\Fixtures\Models\User;

class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->text(rand(10, 30)),
            'content' => fake()->paragraph(),
        ];
    }

    public function modelName(): string
    {
        return Post::class;
    }
}
