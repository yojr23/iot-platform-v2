<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * EVT-04: thrown by `NotificationService::notifyDangerAlertByEmailOrFail()` only when a danger
 * alert email was actually attempted and the underlying send failed (e.g. SMTP unreachable) —
 * never for the intentional no-op outcomes (not a danger alert, no associated reading, rate
 * limited). `SendDangerAlertEmailJob` lets this bubble so the queue worker retries the job.
 *
 * Existing code reused: none new besides this marker exception — `NotificationService` keeps sole
 * ownership of the severity gate/rate-limit/payload-mapping policy this exception distinguishes
 * from a real delivery failure.
 */
class DangerAlertEmailDeliveryException extends RuntimeException {}
