<?php

namespace App\Support;

use Symfony\Component\Mime\Email;

/**
 * Keeps a recurring email in one conversation in the recipient's inbox.
 *
 * Mail apps group messages that share a subject and point at the same
 * message in `References` / `In-Reply-To`. Each recurring email (the daily
 * summary, the overdue digest) therefore keeps a fixed subject and refers to
 * one id that is fixed per kind and per recipient. Each send still gets its
 * own Message-ID, so nothing is ever deduplicated away. `Thread-Topic` is for
 * Outlook, which groups by topic rather than by references.
 */
final class MailThread
{
    public static function root(string $kind, string $recipient): string
    {
        $domain = str((string) config('mail.from.address'))->after('@')->value() ?: 'localhost';

        return $kind.'.'.substr(hash('sha256', strtolower(trim($recipient))), 0, 24).'@'.$domain;
    }

    public static function apply(Email $message, string $kind, string $recipient, string $topic): void
    {
        $root = self::root($kind, $recipient);
        $headers = $message->getHeaders();

        $headers->addIdHeader('In-Reply-To', $root);
        $headers->addIdHeader('References', $root);
        $headers->addTextHeader('Thread-Topic', $topic);
    }
}
