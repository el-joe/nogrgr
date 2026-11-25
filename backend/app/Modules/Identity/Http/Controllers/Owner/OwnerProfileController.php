<?php

namespace App\Modules\Identity\Http\Controllers\Owner;

use App\Modules\Identity\Enums\SocialIntent;
use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Http\Requests\Owner\UpdateOwnerPasswordRequest;
use App\Modules\Identity\Http\Requests\Owner\UpdateOwnerProfileRequest;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Services\OwnerProfileService;
use App\Modules\Identity\Services\SocialAuthService;
use App\Shared\Http\Controller;
use App\Shared\Support\Locale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Owner portal › Profile (OWN-006, Figma Affiliate & Owner 122:104562): personal
 * information, password, and linking / unlinking Google and Apple (the link goes
 * through the shared {central}/auth/{provider} callback with intent "link").
 */
class OwnerProfileController extends Controller
{
    public function __construct(
        private readonly OwnerProfileService $profiles,
        private readonly SocialAuthService $social,
    ) {}

    public function edit(Request $request): View
    {
        $owner = $this->owner($request);
        $providers = [];

        foreach (SocialProvider::cases() as $provider) {
            $providers[] = [
                'provider' => $provider,
                'linked' => $this->profiles->linked($owner, $provider),
                'enabled' => $provider->isEnabled(),
                'canUnlink' => $this->profiles->canUnlink($owner, $provider),
            ];
        }

        return view('identity::owner.profile', [
            'owner' => $owner,
            'providers' => $providers,
            'hasPassword' => $owner->password !== null,
            'locales' => collect(Locale::enabled())->map(fn (array $locale): string => $locale['native'])->all(),
        ]);
    }

    public function update(UpdateOwnerProfileRequest $request): RedirectResponse
    {
        $this->profiles->update($this->owner($request), $request->toData());

        return redirect()->route('owner.profile.edit')->with('success', __('identity::owner_profile.saved'));
    }

    public function password(UpdateOwnerPasswordRequest $request): RedirectResponse
    {
        $this->profiles->changePassword($this->owner($request), $request->password());

        return redirect()->route('owner.profile.edit')->with('success', __('identity::owner_profile.password_saved'));
    }

    public function link(Request $request, string $provider): RedirectResponse
    {
        $social = SocialProvider::tryFrom($provider);
        abort_if($social === null || ! $social->isEnabled(), 404);

        return redirect()->away($this->social->begin($social, SocialIntent::Link, $request));
    }

    public function unlink(Request $request, string $provider): RedirectResponse
    {
        $social = SocialProvider::tryFrom($provider);
        abort_if($social === null, 404);

        try {
            $this->profiles->unlink($this->owner($request), $social);
        } catch (ValidationException $exception) {
            return redirect()->route('owner.profile.edit')->withErrors($exception->errors())->with('error', $exception->getMessage());
        }

        return redirect()->route('owner.profile.edit')->with('success', __('identity::owner_profile.unlinked', ['provider' => $social->brand()]));
    }

    private function owner(Request $request): Owner
    {
        /** @var Owner $owner */
        $owner = $request->user('owner');

        return $owner;
    }
}
