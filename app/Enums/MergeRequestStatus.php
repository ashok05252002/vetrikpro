<?php

namespace App\Enums;

/**
 * A merge request's lifecycle. The allowed moves live here, in one table, so
 * every screen and the service that applies them agree:
 *
 *   open ──approve──▶ approved ──merge──▶ merged
 *     │  ◀─resubmit─┐    │
 *     └─request changes─▶ changes_requested
 *   any live state ──close──▶ closed
 */
enum MergeRequestStatus: string
{
    case Open = 'open';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Merged = 'merged';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Awaiting review',
            self::ChangesRequested => 'Changes requested',
            self::Approved => 'Approved',
            self::Merged => 'Merged',
            self::Closed => 'Closed',
        };
    }

    /** Still in play: blocks a second request on the same branch. */
    public function isLive(): bool
    {
        return ! in_array($this, [self::Merged, self::Closed], true);
    }

    /**
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Open => [self::Approved, self::ChangesRequested, self::Closed],
            self::ChangesRequested => [self::Open, self::Closed],
            // A reviewer can still pull an approval back before merging.
            self::Approved => [self::Merged, self::ChangesRequested, self::Closed],
            self::Merged, self::Closed => [],
        };
    }

    public function canBecome(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    /**
     * @return list<string>
     */
    public static function live(): array
    {
        return array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isLive()));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
