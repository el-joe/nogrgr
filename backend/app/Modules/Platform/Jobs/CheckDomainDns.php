<?php

namespace App\Modules\Platform\Jobs;

use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Repositories\Contracts\DomainRequestRepositoryContract;
use App\Modules\Platform\Services\DomainService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * DNS + SSL check of one custom-domain request (network I/O, so always queued).
 */
class CheckDomainDns implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    public function __construct(public readonly int $requestId) {}

    public function uniqueId(): string
    {
        return (string) $this->requestId;
    }

    public function handle(DomainRequestRepositoryContract $requests, DomainService $domains): void
    {
        $request = $requests->find($this->requestId);

        if ($request instanceof DomainRequest) {
            $domains->runCheck($request);
        }
    }
}
