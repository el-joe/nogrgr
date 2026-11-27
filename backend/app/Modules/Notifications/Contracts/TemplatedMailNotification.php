<?php

namespace App\Modules\Notifications\Contracts;

use App\Modules\Notifications\Enums\EmailTemplateEvent;

/**
 * A Notification whose mail is an editable registry template (decision #118). The
 * architecture test requires it of every Notification with a toMail(); the
 * EnforceEmailTemplateSwitches listener drops the mail when the template is disabled.
 */
interface TemplatedMailNotification
{
    /**
     * Every template key this notification class can send.
     *
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array;

    /**
     * The template this instance sends to the recipient.
     */
    public function mailTemplate(object $notifiable): EmailTemplateEvent;
}
