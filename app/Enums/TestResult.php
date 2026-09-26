<?php

namespace App\Enums;

/**
 * The outcome of one testing point within one run. Blocked means it could not
 * be run at all (environment down, data missing) — not that it failed.
 */
enum TestResult: string
{
    case NotRun = 'not_run';
    case Passed = 'passed';
    case Failed = 'failed';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::NotRun => 'Not run',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
            self::Blocked => 'Blocked',
        };
    }

    /**
     * A retest's verdict carries through to the bug: a pass closes it, a
     * fail sends it back as Repeated. Blocked and Not run leave it alone.
     */
    public function pointStatus(): ?TestPointStatus
    {
        return match ($this) {
            self::Passed => TestPointStatus::Closed,
            self::Failed => TestPointStatus::Repeated,
            default => null,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
