<?php

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Enums\RequestKind;

/**
 * A vendor request with a conversation (product / manufacturing / brand). Implemented by
 * the request models through Concerns\HasRequestThread; used by RequestThreadService,
 * RequestPaymentService and the shared thread Blade component.
 */
interface ThreadedRequest
{
    public function kind(): RequestKind;

    /**
     * Display reference ("PR-2041").
     */
    public function reference(): string;

    /**
     * Short subject line (product name / brand name).
     */
    public function subject(): string;

    public function statusLabel(): string;

    public function statusTone(): string;

    public function tenantId(): int;
}
