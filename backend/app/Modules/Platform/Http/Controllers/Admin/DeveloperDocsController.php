<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Platform\Enums\DeveloperDocTopic;
use App\Modules\Platform\Services\DeveloperDocsService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;

/**
 * Admin › System tools › Developer docs (ADM-115 index / ADM-115a topic, Figma 80:94467):
 * topic list + the selected topic (the index opens "Getting started").
 */
class DeveloperDocsController extends Controller
{
    public function __construct(private readonly DeveloperDocsService $docs) {}

    public function index(): View
    {
        return $this->render(DeveloperDocTopic::GettingStarted);
    }

    public function show(DeveloperDocTopic $topic): View
    {
        return $this->render($topic);
    }

    private function render(DeveloperDocTopic $topic): View
    {
        return view('platform::admin.system.docs.show', [
            'topics' => $this->docs->topics(),
            'current' => $topic,
            'doc' => $this->docs->topic($topic),
        ]);
    }
}
