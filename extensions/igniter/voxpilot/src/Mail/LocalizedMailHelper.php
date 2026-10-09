<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Mail;

use Igniter\System\Helpers\MailHelper;
use Igniter\System\Mail\AnonymousTemplateMailable;
use Igniter\User\Models\User;
use Igniter\VoxPilot\Support\Locale;
use Illuminate\Support\Facades\Mail;

/**
 * TastyIgniter queues staff emails (invite, password reset…) in the locale of the request that
 * triggers them: English for a signed-out "forgot password". This sends them in the staff member's
 * own language instead, the one VoxPilot stores when it provisions the owner.
 */
class LocalizedMailHelper extends MailHelper
{
    public function sendTemplate(string $view, array $vars, $callback = null)
    {
        return Mail::send($this->mailable($view, $vars, $callback));
    }

    public function queueTemplate(string $view, array $vars, $callback = null)
    {
        return Mail::queue($this->mailable($view, $vars, $callback));
    }

    protected function mailable(string $view, array $vars, $callback): AnonymousTemplateMailable
    {
        $mailable = AnonymousTemplateMailable::create($view)->applyCallback($callback)->withSerializedData($vars);

        $staff = $vars['staff'] ?? null;
        if ($staff instanceof User && ($locale = Locale::match($staff->getLocale()))) {
            $mailable->locale($locale);
        }

        return $mailable;
    }
}
