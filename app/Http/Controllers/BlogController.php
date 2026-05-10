<?php

namespace App\Http\Controllers;

use App\Support\BlogRepository;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function __construct(private readonly BlogRepository $blog) {}

    public function index(): Response
    {
        return Inertia::render('Blog/Index', [
            'posts' => $this->blog->all(),
        ]);
    }

    public function show(string $slug): Response
    {
        $post = $this->blog->findBySlug($slug);

        abort_if($post === null, 404);

        return Inertia::render('Blog/Show', [
            'post' => $post,
        ]);
    }
}
