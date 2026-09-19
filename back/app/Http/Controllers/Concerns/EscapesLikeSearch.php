<?php

namespace App\Http\Controllers\Concerns;

trait EscapesLikeSearch
{
    /**
     * Escape LIKE metacharacters so a user search term matches literally.
     *
     * Pairs with an explicit `ESCAPE '~'` clause. `~` (not `\`) is used as the escape
     * character because MySQL's string-literal parser would reprocess a backslash in
     * `ESCAPE '\'`, producing an unterminated literal (1064 syntax error). `~` needs no
     * such escaping and behaves identically on MySQL and SQLite.
     */
    protected static function escapeLike(string $search): string
    {
        return str_replace(['~', '%', '_'], ['~~', '~%', '~_'], $search);
    }
}
