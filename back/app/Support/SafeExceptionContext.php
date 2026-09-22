<?php

namespace App\Support;

use Throwable;

class SafeExceptionContext
{
    /**
     * @return array{exception_class: class-string<Throwable>, exception_code: int, fingerprint: string, previous_exception_class: class-string<Throwable>|null}
     */
    public static function from(Throwable $exception): array
    {
        $previousExceptionClass = $exception->getPrevious() ? $exception->getPrevious()::class : null;

        return [
            'exception_class' => $exception::class,
            'exception_code' => (int) $exception->getCode(),
            'fingerprint' => hash(
                'sha256',
                $exception::class.'|'.$exception->getCode().'|'.($previousExceptionClass ?? ''),
            ),
            'previous_exception_class' => $previousExceptionClass,
        ];
    }
}
