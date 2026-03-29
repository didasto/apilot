<?php

declare(strict_types=1);

namespace Didasto\Apilot\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'title'  => $this->faker->sentence(3),
            'body'   => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
        ];
    }
}
