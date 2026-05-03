<?php

namespace Tests\Feature;

use App\Support\BlogRepository;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BlogTest extends TestCase
{
    private string $blogPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blogPath = resource_path('markdown/blog');
    }

    public function test_blog_index_is_accessible(): void
    {
        $response = $this->get(route('blog.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Blog/Index')->has('posts'));
    }

    public function test_blog_index_returns_posts_sorted_by_date(): void
    {
        $response = $this->get(route('blog.index'));

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $posts = $page->toArray()['props']['posts'];

            if (count($posts) > 1) {
                for ($i = 0; $i < count($posts) - 1; $i++) {
                    $this->assertGreaterThanOrEqual($posts[$i + 1]['date'], $posts[$i]['date']);
                }
            }

            return true;
        });
    }

    public function test_blog_show_returns_post_for_valid_slug(): void
    {
        $repo = app(BlogRepository::class);
        $posts = $repo->all();

        if (empty($posts)) {
            $this->markTestSkipped('No blog posts available.');
        }

        $slug = $posts[0]['slug'];
        $response = $this->get(route('blog.show', $slug));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Blog/Show')
            ->has('post')
            ->where('post.slug', $slug)
            ->has('post.body')
        );
    }

    public function test_blog_show_returns_404_for_unknown_slug(): void
    {
        $response = $this->get(route('blog.show', 'this-slug-does-not-exist'));

        $response->assertNotFound();
    }

    public function test_welcome_page_includes_latest_post(): void
    {
        $repo = app(BlogRepository::class);
        $latest = $repo->latest();

        if ($latest === null) {
            $this->markTestSkipped('No blog posts available.');
        }

        $response = $this->get(route('welcome'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('latestPost')
            ->where('latestPost.slug', $latest['slug'])
        );
    }

    public function test_blog_repository_parses_frontmatter(): void
    {
        $tmpFile = $this->blogPath.'/test-post.md';

        File::put($tmpFile, <<<'MD'
            ---
            title: Test Post
            date: 2026-01-01
            slug: test-post
            excerpt: A test excerpt.
            author: Test Author
            tags: [test, blog]
            ---

            This is the body.
            MD);

        try {
            $repo = app(BlogRepository::class);
            $post = $repo->findBySlug('test-post');

            $this->assertNotNull($post);
            $this->assertEquals('Test Post', $post['title']);
            $this->assertEquals('2026-01-01', $post['date']);
            $this->assertEquals('test-post', $post['slug']);
            $this->assertEquals('A test excerpt.', $post['excerpt']);
            $this->assertEquals('Test Author', $post['author']);
            $this->assertContains('test', $post['tags']);
            $this->assertStringContainsString('This is the body', $post['body']);
        } finally {
            File::delete($tmpFile);
        }
    }

    public function test_blog_repository_skips_files_without_frontmatter(): void
    {
        $tmpFile = $this->blogPath.'/no-frontmatter.md';

        File::put($tmpFile, 'Just plain content without frontmatter.');

        try {
            $repo = app(BlogRepository::class);
            $post = $repo->findBySlug('no-frontmatter');

            $this->assertNull($post);
        } finally {
            File::delete($tmpFile);
        }
    }
}
