<?php

namespace Tests\Feature;

use App\Console\Commands\CapacityCheck;
use App\Mail\CapacityAlertMail;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapacityCheckTest extends TestCase
{
    use RefreshDatabase;

    private function run_with(array $metrics): int
    {
        $this->app[Kernel::class]->registerCommand(new class($metrics) extends CapacityCheck {
            public function __construct(private array $m)
            {
                parent::__construct();
            }

            protected function measure(): array
            {
                return $this->m;
            }
        });

        return $this->artisan('iot:capacity:check')->run();
    }

    public function test_ok_does_not_mail_and_unavailable_metrics_are_skipped(): void
    {
        Mail::fake();
        $this->assertSame(0, $this->run_with(['disk' => [50, 100], 'redis_memory' => null]));
        Mail::assertNothingSent();
    }

    public function test_warn_and_critical_mail_once_per_metric_per_six_hours(): void
    {
        Mail::fake();
        Cache::flush();
        config(['mail.recipient_email' => 'admin@example.test']);

        $this->assertSame(0, $this->run_with(['disk' => [85, 100]]));   // WARN
        $this->assertSame(2, $this->run_with(['disk' => [95, 100]]));   // CRITICAL, same metric: deduped
        Mail::assertSent(CapacityAlertMail::class, 1);

        $this->run_with(['redis_memory' => [95, 100]]);                 // other metric: its own mail
        Mail::assertSent(CapacityAlertMail::class, 2);
        Mail::assertSent(CapacityAlertMail::class, fn ($m) => $m->level === 'CRITICAL' && $m->hasTo('admin@example.test'));
    }
}
