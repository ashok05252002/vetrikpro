<?php

namespace App\Enums;

/**
 * The life of a reported bug. A tester reports it (Open); the developer it is
 * assigned to works on it (In progress) and hands it back (Ready for test);
 * the tester retests — a pass closes it, a fail sends it back as Repeated,
 * and the loop runs again from In progress.
 */
enum TestPointStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case ReadyForTest = 'ready_for_test';
    case Repeated = 'repeated';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::ReadyForTest => 'Ready for test',
            self::Repeated => 'Repeated',
            self::Closed => 'Closed',
        };
    }

    /**
     * A tester's verdict on a retest: closed (passed) or repeated (failed
     * again). Reaching one records who tested it and when.
     */
    public function isOutcome(): bool
    {
        return $this === self::Closed || $this === self::Repeated;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
