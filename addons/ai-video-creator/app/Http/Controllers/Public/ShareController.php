<?php

namespace Addons\AiVideoCreator\Http\Controllers\Public;

use Addons\AiVideoCreator\Models\VcRender;
use Illuminate\Routing\Controller;

class ShareController extends Controller
{
    public function show(string $token): \Inertia\Response
    {
        $render = VcRender::where('share_token', $token)
            ->where('share_enabled', true)
            ->where('status', 'completed')
            ->firstOrFail();

        $pageTitle = $render->title ?: translate('Shared Video');

        return inertia('Addons/ai-video-creator/Public/Share', [
            'render' => [
                'title' => $render->title,
                'file_url' => $render->file_url,
                'thumbnail_url' => $render->thumbnail_url,
                'duration_actual' => $render->duration_actual,
            ],
            'seo' => [
                'title' => document_title($pageTitle),
                // A share token is "unlisted", not public: the owner handed a link to
                // specific people. app.blade.php derives robots from the request path and
                // /video/* is not in its private list, so without this the page is
                // indexable and a shared video becomes findable on Google. No canonical
                // either — nothing here should be consolidated into the index.
                'robots' => 'noindex, nofollow',
            ],
        ]);
    }
}
