# Testing

## Testing Your Own Controllers

Apilot controllers are standard Laravel controllers. Test them with Laravel's built-in HTTP testing tools.

### Setup

```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    public function testIndexReturnsPaginatedPosts(): void
    {
        Post::factory()->count(30)->create();

        $response = $this->getJson('/api/posts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'  => [['id', 'title']],
                'meta'  => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function testStoreCreatesPost(): void
    {
        $response = $this->postJson('/api/posts', [
            'title'  => 'New Post',
            'body'   => 'Content here.',
            'status' => 'draft',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'New Post');

        $this->assertDatabaseHas('posts', ['title' => 'New Post']);
    }

    public function testShowReturnsPost(): void
    {
        $post = Post::factory()->create(['title' => 'Hello World']);

        $this->getJson("/api/posts/{$post->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Hello World');
    }

    public function testShowReturns404ForMissingPost(): void
    {
        $this->getJson('/api/posts/999')
            ->assertStatus(404)
            ->assertJsonPath('error.status', 404);
    }

    public function testUpdateModifiesPost(): void
    {
        $post = Post::factory()->create(['title' => 'Old Title']);

        $this->putJson("/api/posts/{$post->id}", ['title' => 'New Title'])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'New Title');
    }

    public function testDestroyDeletesPost(): void
    {
        $post = Post::factory()->create();

        $this->deleteJson("/api/posts/{$post->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }
}
```

### Testing Filters

```php
public function testFilterByStatus(): void
{
    Post::factory()->create(['status' => 'published']);
    Post::factory()->create(['status' => 'draft']);

    $response = $this->getJson('/api/posts?filter[status]=published');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.status', 'published');
}
```

### Testing Sorting

```php
public function testSortByTitleAscending(): void
{
    Post::factory()->create(['title' => 'B Post']);
    Post::factory()->create(['title' => 'A Post']);

    $response = $this->getJson('/api/posts?sort=title');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.title', 'A Post')
        ->assertJsonPath('data.1.title', 'B Post');
}
```

### Testing Authentication

```php
public function testStoreRequiresAuth(): void
{
    $this->postJson('/api/posts', ['title' => 'Test'])
        ->assertStatus(401);
}

public function testAuthenticatedUserCanStore(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/posts', ['title' => 'Test', 'body' => '...', 'status' => 'draft'])
        ->assertStatus(201);
}
```

### Testing Hooks

```php
public function testBeforeDestroyPreventsUnauthorizedDeletion(): void
{
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $post  = Post::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->deleteJson("/api/posts/{$post->id}")
        ->assertStatus(403)
        ->assertJsonPath('error.status', 403);

    $this->assertDatabaseHas('posts', ['id' => $post->id]);
}
```

## Running the Package's Own Tests

The package uses PHPUnit 11 with an SQLite in-memory database via [Orchestra Testbench](https://github.com/orchestral/testbench).

Since there is no PHP installed on the host, all commands run in Docker:

```bash
# Run all tests
docker run --rm -v $(pwd):/app -w /app composer php ./vendor/bin/phpunit

# Run a specific test file
docker run --rm -v $(pwd):/app -w /app composer php ./vendor/bin/phpunit --filter=EmptyControllerTest

# Run a specific test method
docker run --rm -v $(pwd):/app -w /app composer php ./vendor/bin/phpunit --filter=testMinimalControllerIndexReturnsResults

# Run with verbose output
docker run --rm -v $(pwd):/app -w /app composer php ./vendor/bin/phpunit --verbose

# Install dependencies first
docker run --rm -v $(pwd):/app -w /app composer install
```

## Test Organization

The package test suite covers:

| Directory | Description |
|-----------|-------------|
| `tests/Unit/` | Pure unit tests (DTOs, SchemaBuilder, RouteRegistry) |
| `tests/Feature/` | Full HTTP tests with an in-memory Laravel app |
| `tests/Feature/EdgeCases/` | Edge cases: malformed input, empty controllers, large datasets |
| `tests/Feature/Integration/` | End-to-end lifecycle tests and spec validation |
| `tests/Fixtures/` | Shared models, controllers, requests, and resources used by tests |

---

**Next:** [Advanced Examples](15-advanced-examples.md)
