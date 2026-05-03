<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BlogRepository
{
    private string $blogPath;

    public function __construct()
    {
        $this->blogPath = resource_path('markdown/blog');
    }

    /**
     * Return all blog posts sorted by date descending.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        if (! File::isDirectory($this->blogPath)) {
            return [];
        }

        $files = File::glob("{$this->blogPath}/*.md");

        $posts = collect($files)
            ->map(fn (string $file) => $this->parseMeta($file))
            ->filter()
            ->sortByDesc('date')
            ->values()
            ->all();

        return $posts;
    }

    /**
     * Return a single post by slug, with rendered HTML body, or null if not found.
     *
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        if (! File::isDirectory($this->blogPath)) {
            return null;
        }

        $files = File::glob("{$this->blogPath}/*.md");

        foreach ($files as $file) {
            $meta = $this->parseMeta($file);
            if ($meta && $meta['slug'] === $slug) {
                return $this->parsePost($file, $meta);
            }
        }

        return null;
    }

    /**
     * Return the most recent post (meta only, no rendered body).
     *
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        $all = $this->all();

        return $all[0] ?? null;
    }

    /**
     * Parse frontmatter and return post metadata (no body rendering).
     *
     * @return array<string, mixed>|null
     */
    private function parseMeta(string $file): ?array
    {
        $contents = File::get($file);
        $parsed = $this->splitFrontmatter($contents);

        if ($parsed === null) {
            return null;
        }

        [$frontmatter, $body] = $parsed;

        $title = $this->extractScalar($frontmatter, 'title');
        $date = $this->extractScalar($frontmatter, 'date');
        $slug = $this->extractScalar($frontmatter, 'slug') ?? $this->slugFromFilename($file);
        $excerpt = $this->extractScalar($frontmatter, 'excerpt') ?? $this->autoExcerpt($body);
        $author = $this->extractScalar($frontmatter, 'author') ?? 'FlashView Team';
        $tags = $this->extractList($frontmatter, 'tags');

        if (! $title || ! $date) {
            return null;
        }

        return [
            'title' => $title,
            'date' => $date,
            'date_formatted' => Carbon::parse($date)->format('d M Y'),
            'slug' => $slug,
            'excerpt' => $excerpt,
            'author' => $author,
            'tags' => $tags,
        ];
    }

    /**
     * Parse a post including the rendered HTML body.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function parsePost(string $file, array $meta): array
    {
        $contents = File::get($file);
        $parsed = $this->splitFrontmatter($contents);
        $body = $parsed ? $parsed[1] : $contents;

        return array_merge($meta, [
            'body' => Str::markdown($body),
        ]);
    }

    /**
     * Split a markdown file into [frontmatter, body]. Returns null if no frontmatter block.
     *
     * @return array{string, string}|null
     */
    private function splitFrontmatter(string $contents): ?array
    {
        if (! str_starts_with($contents, '---')) {
            return null;
        }

        $end = strpos($contents, '---', 3);
        if ($end === false) {
            return null;
        }

        $frontmatter = substr($contents, 3, $end - 3);
        $body = ltrim(substr($contents, $end + 3));

        return [$frontmatter, $body];
    }

    private function extractScalar(string $frontmatter, string $key): ?string
    {
        if (preg_match('/^'.preg_quote($key, '/').':\s*(.+)$/m', $frontmatter, $matches)) {
            return trim($matches[1], " '\"\t\r\n");
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function extractList(string $frontmatter, string $key): array
    {
        if (! preg_match('/^'.preg_quote($key, '/').':\s*\[([^\]]*)\]/m', $frontmatter, $matches)) {
            return [];
        }

        return array_map(
            fn (string $t) => trim($t, " '\"\t"),
            explode(',', $matches[1]),
        );
    }

    private function slugFromFilename(string $file): string
    {
        $base = basename($file, '.md');

        // Strip leading date prefix (YYYY-MM-DD-)
        return preg_replace('/^\d{4}-\d{2}-\d{2}-/', '', $base) ?? $base;
    }

    private function autoExcerpt(string $body, int $length = 200): string
    {
        $text = preg_replace('/[#*`_\[\]>]/', '', $body) ?? $body;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($truncated, ' ');

        return ($lastSpace !== false ? mb_substr($truncated, 0, $lastSpace) : $truncated).'…';
    }
}
