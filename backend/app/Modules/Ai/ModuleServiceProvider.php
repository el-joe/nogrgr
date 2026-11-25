<?php

namespace App\Modules\Ai;

use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\Enums\TranslationPurpose;
use App\Modules\Ai\Services\Translation\TranslationClientFactory;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [];

    public function register(): void
    {
        parent::register();

        // Default client = custom translations provider (decision #44); paid runs ask the factory.
        $this->app->bind(TranslationClient::class, static fn ($app): TranslationClient => $app->make(TranslationClientFactory::class)->for(TranslationPurpose::Custom));
    }
}
