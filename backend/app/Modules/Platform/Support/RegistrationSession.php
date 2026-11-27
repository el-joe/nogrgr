<?php

namespace App\Modules\Platform\Support;

use App\Modules\Platform\DTOs\SignUpDraftData;
use Illuminate\Contracts\Session\Session;

/**
 * Where the central sign-up keeps its state between steps: the draft (sign-up form,
 * before a plan is chosen) and the id of the visitor's current registration (plan,
 * payment and verification pages only ever act on that one).
 */
final class RegistrationSession
{
    private const DRAFT = 'central_registration.draft';

    private const CURRENT = 'central_registration.current';

    private const SOCIAL = 'central_registration.social';

    private const PLAN = 'central_registration.plan';

    private const THEME = 'central_registration.theme';

    public function __construct(private readonly Session $session) {}

    public function draft(): ?SignUpDraftData
    {
        $draft = $this->session->get(self::DRAFT);

        return is_array($draft) ? SignUpDraftData::fromArray($draft) : null;
    }

    public function putDraft(SignUpDraftData $draft): void
    {
        $this->session->put(self::DRAFT, $draft->toArray());
    }

    public function forgetDraft(): void
    {
        $this->session->forget(self::DRAFT);
    }

    public function currentId(): ?int
    {
        $id = $this->session->get(self::CURRENT);

        return is_numeric($id) ? (int) $id : null;
    }

    public function setCurrent(int $registrationId): void
    {
        $this->session->put(self::CURRENT, $registrationId);
    }

    /**
     * Sign-up fields kept while the visitor is away at Google / Apple.
     *
     * @param  array{phone: ?string, compliance_accepted_at: string, locale: string}  $fields
     */
    public function putSocialPending(array $fields): void
    {
        $this->session->put(self::SOCIAL, $fields);
    }

    /**
     * @return array{phone: ?string, compliance_accepted_at: string, locale: string}|null
     */
    public function pullSocialPending(): ?array
    {
        $fields = $this->session->pull(self::SOCIAL);

        return is_array($fields) && is_string($fields['compliance_accepted_at'] ?? null) ? [
            'phone' => is_string($fields['phone'] ?? null) ? $fields['phone'] : null,
            'compliance_accepted_at' => $fields['compliance_accepted_at'],
            'locale' => is_string($fields['locale'] ?? null) ? $fields['locale'] : app()->getLocale(),
        ] : null;
    }

    /**
     * Plan picked on the pricing page (?plan=&term=), pre-selected on the plan step.
     *
     * @param  array{plan: int, term: string}  $plan
     */
    public function putPreselectedPlan(array $plan): void
    {
        $this->session->put(self::PLAN, $plan);
    }

    /**
     * @return array{plan: int, term: string}|null
     */
    public function preselectedPlan(): ?array
    {
        $plan = $this->session->get(self::PLAN);

        return is_array($plan) && is_int($plan['plan'] ?? null) && is_string($plan['term'] ?? null) ? $plan : null;
    }

    /**
     * Catalogue theme picked on the central theme preview (WEB-004 "Start with this theme").
     */
    public function putPreselectedTheme(int $themeId): void
    {
        $this->session->put(self::THEME, $themeId);
    }

    public function preselectedTheme(): ?int
    {
        $theme = $this->session->get(self::THEME);

        return is_int($theme) ? $theme : null;
    }

    public function forget(): void
    {
        $this->session->forget([self::DRAFT, self::CURRENT, self::SOCIAL, self::PLAN, self::THEME]);
    }
}
