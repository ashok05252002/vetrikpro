<?php

namespace App\Models\Concerns;

use App\Enums\TaskPriority;
use App\Models\Task;
use App\Models\User;
use App\Notifications\WorkAssigned;
use App\Notifications\WorkStatusChanged;
use App\Support\SendsMailSafely;
use App\Support\Settings;

/**
 * Emails about tasks and bugs, as Configuration hub → Email notifications
 * sets them: to the new assignee when work is assigned, and to the assignee
 * and the reporter when it moves into a status that is switched on.
 *
 * Sent from the model, so it holds however the change arrived — dialog, board
 * drag, list dropdown, a test run. Never for your own action, never with nobody
 * signed in (seeders and console commands stay silent), and only after commit —
 * after the response, and never failing the request (SendsMailSafely).
 */
trait SendsWorkMail
{
    /**
     * Set by the `created` event and cleared after the save it belongs to:
     * wasRecentlyCreated stays true for the object's whole life, so a later
     * update on the same object would otherwise look like a creation.
     */
    protected bool $creatingForMail = false;

    public static function bootSendsWorkMail(): void
    {
        static::created(fn (self $card) => $card->creatingForMail = true);
        static::saved(function (self $card) {
            $card->sendWorkMail($card->creatingForMail);
            $card->creatingForMail = false;
        });
    }

    public function sendWorkMail(bool $created = false): void
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        $settings = app(Settings::class);
        $kind = $this instanceof Task ? 'task' : 'bug';
        $told = [$actor->id];
        $mail = [];

        $assigned = $this->assigned_to !== null && ($created || $this->wasChanged('assigned_to'));
        // An urgent task handed over already gets the urgent email; one is enough.
        $urgentCovers = $this instanceof Task && $this->priority === TaskPriority::Urgent && $settings->get('notify.task.urgent');

        if ($assigned && ! $urgentCovers && $settings->get("notify.{$kind}.assigned") && ! in_array($this->assigned_to, $told, true)) {
            $mail[] = [$this->assigned_to, fn () => new WorkAssigned($this, $actor)];
            $told[] = $this->assigned_to;
        }

        if (! $created && $this->wasChanged('status') && in_array($this->status->value, (array) $settings->get("notify.{$kind}.status"), true)) {
            $original = $this->getOriginal('status');
            $from = $original instanceof \BackedEnum ? $original->label() : (string) $original;

            foreach (array_unique(array_filter([$this->assigned_to, $this->created_by])) as $userId) {
                if (! in_array($userId, $told, true)) {
                    $mail[] = [$userId, fn () => new WorkStatusChanged($this, $actor, $from)];
                    $told[] = $userId;
                }
            }
        }

        if ($mail === []) {
            return;
        }

        SendsMailSafely::afterCommit(function () use ($mail) {
            $people = User::whereIn('id', array_column($mail, 0))->where('is_active', true)->get()->keyBy('id');

            foreach ($mail as [$userId, $notification]) {
                // One person's bad address must not stop the others' email.
                rescue(fn () => $people->get($userId)?->notify($notification()));
            }
        });
    }
}
