<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Turso\Driver\Laravel\Tests\Fixtures\Models\Comment;
use Turso\Driver\Laravel\Tests\Fixtures\Models\Post;
use Turso\Driver\Laravel\Tests\Fixtures\Models\User;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'content' => $this->faker->paragraph(), // Generates random text content for comments
        ];
    }
}
