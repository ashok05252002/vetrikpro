<?php

namespace App\Services;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Places a card at a position in a board column and renumbers the column so
 * positions stay dense. Shared by the task and testing boards.
 *
 * The project row is locked for the duration, so two people dragging cards
 * on the same board are applied one after the other instead of interleaving
 * their renumbering and leaving duplicate positions behind.
 */
final class BoardOrdering
{
    public static function place(Model $card, BackedEnum $status, int $position): void
    {
        DB::transaction(function () use ($card, $status, $position) {
            $card->project()->lockForUpdate()->value('id');

            $siblings = $card->newQuery()
                ->where('project_id', $card->project_id)
                ->where('status', $status)
                ->whereKeyNot($card->getKey())
                ->orderBy('position')
                ->pluck('id')
                ->all();

            array_splice($siblings, min($position, count($siblings)), 0, [$card->getKey()]);

            foreach ($siblings as $index => $id) {
                $card->newQuery()->whereKey($id)->update(['position' => $index]);
            }

            // Through the model, so derived columns (completed_at, last_tested_at) follow the status.
            $card->update(['status' => $status, 'position' => array_search($card->getKey(), $siblings, true)]);
        });
    }

    public static function nextPosition(Model $card, BackedEnum $status): int
    {
        return (int) $card->newQuery()
            ->where('project_id', $card->project_id)
            ->where('status', $status)
            ->max('position') + 1;
    }
}
