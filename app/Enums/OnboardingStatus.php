<?php

namespace App\Enums;

/**
 * invited → in_progress → submitted → completed, with HR able to send a
 * submission back (returned), which the employee fixes and resubmits.
 */
enum OnboardingStatus: string
{
    case Invited = 'invited';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::InProgress => 'In progress',
            self::Submitted => 'Awaiting review',
            self::Returned => 'Sent back',
            self::Completed => 'Completed',
        };
    }

    /** The employee still has something to do, so the portal sends them to onboarding. */
    public function needsEmployee(): bool
    {
        return in_array($this, [self::Invited, self::InProgress, self::Returned], true);
    }

    /** The employee may still change what they entered. */
    public function isEditable(): bool
    {
        return $this->needsEmployee();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
