<?php

declare(strict_types=1);

namespace App\Enum;

/** How one attempt to hand a mail to the relay ended — see {@see \App\Entity\EmailLog}. */
enum EmailLogStatus: string
{
    /** The relay accepted it. (Accepted is not delivered — a later bounce is not known here.) */
    case Sent = 'sent';

    /** The relay refused it or could not be reached; the queue may still retry. */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Odesláno',
            self::Failed => 'Chyba',
        };
    }
}
