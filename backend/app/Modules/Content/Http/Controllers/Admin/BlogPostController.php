<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Modules\Content\DataTables\BlogPostsDataTable;
use App\Modules\Content\Enums\PostStatus;
use App\Modules\Content\Http\Requests\Admin\BlogPostRequest;
use App\Modules\Content\Models\BlogPost;
use App\Modules\Content\Repositories\Contracts\BlogCategoryRepositoryContract;
use App\Modules\Content\Services\BlogPostService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Blog posts (ADM-081 list: publish, archive, delete; ADM-081a create / edit with SEO
 * fields and cover). Permissions: content.posts.view / .manage.
 */
class BlogPostController extends Controller
{
    public function __construct(
        private readonly BlogPostService $service,
        private readonly BlogCategoryRepositoryContract $categories,
    ) {}

    public function index(): View
    {
        return view('content::admin.posts.index', [
            'statuses' => PostStatus::options(),
            'categories' => $this->categories->options(),
        ]);
    }

    public function data(Request $request, BlogPostsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, ['status', 'category', 'search']);
    }

    public function create(): View
    {
        return $this->form(new BlogPost(['status' => PostStatus::Draft]));
    }

    public function store(BlogPostRequest $request): JsonResponse
    {
        $post = $this->service->create($request->toData());

        return JsonResponder::created(__('content::admin.common.created'), ['id' => $post->id], route('admin.content.posts.index'));
    }

    public function edit(BlogPost $post): View
    {
        return $this->form($post);
    }

    public function update(BlogPostRequest $request, BlogPost $post): JsonResponse
    {
        $this->service->update($post, $request->toData());

        return JsonResponder::success(__('content::admin.common.saved'), null, route('admin.content.posts.index'));
    }

    public function publish(BlogPost $post): JsonResponse
    {
        $this->service->publish($post);

        return JsonResponder::success(__('content::admin.posts.published'));
    }

    public function archive(BlogPost $post): JsonResponse
    {
        $this->service->archive($post);

        return JsonResponder::success(__('content::admin.posts.archived'));
    }

    public function destroy(BlogPost $post): JsonResponse
    {
        $this->service->delete($post);

        return JsonResponder::success(__('content::admin.common.deleted'));
    }

    private function form(BlogPost $post): View
    {
        return view('content::admin.posts.form', [
            'post' => $post,
            'statuses' => PostStatus::options(),
            'categories' => $this->categories->options(),
        ]);
    }
}
