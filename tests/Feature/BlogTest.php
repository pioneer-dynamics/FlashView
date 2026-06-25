<?php

use App\Support\BlogRepository;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->blogPath = resource_path('markdown/blog');
});

test('blog index is accessible', function () {
    $response = $this->get(route('blog.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Blog/Index')->has('posts'));
});

test('blog index returns posts sorted by date', function () {
    $response = $this->get(route('blog.index'));

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $posts = $page->toArray()['props']['posts'];

        if (count($posts) > 1) {
            for ($i = 0; $i < count($posts) - 1; $i++) {
                expect($posts[$i]['date'])->toBeGreaterThanOrEqual($posts[$i + 1]['date']);
            }
        }

        return true;
    });
});

test('blog show returns post for valid slug', function () {
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
});

test('blog show returns 404 for unknown slug', function () {
    $response = $this->get(route('blog.show', 'this-slug-does-not-exist'));

    $response->assertNotFound();
});

test('welcome page includes latest post', function () {
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
});

test('blog repository parses frontmatter', function () {
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

        expect($post)->not->toBeNull();
        expect($post['title'])->toEqual('Test Post');
        expect($post['date'])->toEqual('2026-01-01');
        expect($post['slug'])->toEqual('test-post');
        expect($post['excerpt'])->toEqual('A test excerpt.');
        expect($post['author'])->toEqual('Test Author');
        expect($post['tags'])->toContain('test');
        $this->assertStringContainsString('This is the body', $post['body']);
    } finally {
        File::delete($tmpFile);
    }
});

test('blog repository skips files without frontmatter', function () {
    $tmpFile = $this->blogPath.'/no-frontmatter.md';

    File::put($tmpFile, 'Just plain content without frontmatter.');

    try {
        $repo = app(BlogRepository::class);
        $post = $repo->findBySlug('no-frontmatter');

        expect($post)->toBeNull();
    } finally {
        File::delete($tmpFile);
    }
});
