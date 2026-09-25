<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A merge-request action that cannot happen as asked: the state moved on
 * under the user (someone else acted first), the move is not allowed from
 * here, or the branch already has a live request. The message is written
 * for the person who clicked.
 */
class MergeRequestConflict extends RuntimeException {}
