<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Gate 10 live-Docker fault harness support — NOT part of the production delivery path.
 *
 * This lives outside the CDC publication files (ConsumeCdcOutboxes / CdcOutboxStreamConsumer /
 * DebeziumChange) that Gate10NoPollingArchitectureTest forbids from containing sleep/usleep, because
 * this is a deliberate test-only pause to open the otherwise unobservable XADD-before-XACK crash
 * window — the opposite of a polling loop. It only ever runs when the consumer is launched in
 * local/testing with GATE10_CDC_PAUSE_AFTER_PUBLISH_MS set (see
 * CdcOutboxStreamConsumer::faultInjectionPauseAfterPublishMs()).
 */
final class Gate10FaultInjection
{
    /**
     * Build the post-publish/pre-ack hook, or null when no pause is configured (always null in
     * production). The hook emits the exact checkpoint marker the harness greps from `docker logs`
     * (stderr — the default `stack` log channel writes to a file the harness cannot see), then holds
     * the process open so the harness can kill it inside the crash window.
     */
    public static function hook(int $pauseMs): ?\Closure
    {
        if ($pauseMs <= 0) {
            return null;
        }

        return static function (string $stream, string $id) use ($pauseMs): void {
            fwrite(STDERR, sprintf(
                "Gate 10 fault checkpoint reached after publish before ack stream=%s stream_id=%s\n",
                $stream,
                $id,
            ));
            Log::info('Gate 10 fault checkpoint reached after publish before ack', [
                'stream' => $stream,
                'stream_id' => $id,
            ]);
            self::hold($pauseMs);
        };
    }

    private static function hold(int $pauseMs): void
    {
        usleep($pauseMs * 1000);
    }
}
