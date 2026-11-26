<?php

namespace App\Modules\Support\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Support\Enums\HelpTopic;
use App\Modules\Support\Http\Requests\HelpFeedbackRequest;
use App\Modules\Support\Http\Requests\HelpSearchRequest;
use App\Modules\Support\Services\HelpCenterService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Help center (VEN-092 / 092a, Figma 88:112298): search, topics with article
 * counts, a topic's articles, one article and the "Was this helpful?" vote.
 */
class HelpCenterController extends Controller
{
    public function __construct(private readonly HelpCenterService $help) {}

    public function index(HelpSearchRequest $request): View
    {
        $term = $request->term();
        $topic = $request->topic() ?? HelpTopic::GettingStarted;

        return view('support::help.index', [
            'topics' => $this->help->topics(),
            'term' => $term,
            'activeTopic' => $term === '' ? $topic : null,
            'articles' => $term !== '' ? $this->help->search($term) : $this->help->articlesOf($topic),
            'article' => null,
            'vote' => null,
        ]);
    }

    public function article(Request $request, string $slug): View
    {
        $article = $this->help->article($slug) ?? abort(404);
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return view('support::help.index', [
            'topics' => $this->help->topics(),
            'term' => '',
            'activeTopic' => $article['topic'],
            'articles' => [],
            'article' => $article,
            'vote' => $this->help->voteOf((int) $staff->id, $slug),
        ]);
    }

    public function feedback(HelpFeedbackRequest $request, string $slug): JsonResponse
    {
        abort_if(HelpTopic::forArticle($slug) === null, 404);
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');
        $this->help->vote((int) $staff->id, $slug, $request->helpful());

        return JsonResponder::success(__('support::help.feedback.thanks'), ['helpful' => $request->helpful()]);
    }
}
