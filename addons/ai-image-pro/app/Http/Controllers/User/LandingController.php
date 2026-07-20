<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Controllers\User;

use Addons\AiImagePro\Services\ImageAccessService;
use Addons\AiImagePro\Services\LandingPageBuilder;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The public marketing page at /ai-image. The page itself is assembled by
 * LandingPageBuilder — which the homepage provider also uses — so this controller
 * only decides who is allowed to see it here.
 *
 * A signed-in user who already has Studio access does not want a sales page, so
 * they are sent straight to the app. `?preview=1` overrides that, which is how an
 * admin checks their landing copy without signing out.
 *
 * That bounce is this route's rule alone: when the landing page is set as the site
 * homepage it renders at `/` for everyone, signed in or not.
 */
class LandingController extends Controller
{
    public function __construct(
        private readonly ImageAccessService $access,
        private readonly LandingPageBuilder $builder,
    ) {
    }

    public function index(Request $request): Response|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user && ! $request->boolean('preview') && $this->access->canAccessStudio($user)) {
            return redirect()->route('addon.aip.user.studio');
        }

        return $this->builder->render($user);
    }
}
