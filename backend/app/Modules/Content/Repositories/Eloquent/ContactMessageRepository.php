<?php

namespace App\Modules\Content\Repositories\Eloquent;

use App\Modules\Content\Models\ContactMessage;
use App\Modules\Content\Repositories\Contracts\ContactMessageRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<ContactMessage>
 */
class ContactMessageRepository extends BaseRepository implements ContactMessageRepositoryContract
{
    protected function model(): string
    {
        return ContactMessage::class;
    }
}
