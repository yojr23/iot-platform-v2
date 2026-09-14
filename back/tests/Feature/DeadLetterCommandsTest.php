<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class DeadLetterCommandsTest extends TestCase
{
    public function test_replay_requires_explicit_confirmation(): void
    {
        $this->artisan('dlq:replay', ['id' => '1-0', '--source' => 'iot.raw-events'])
            ->expectsOutputToContain('Refusing to replay without --yes')
            ->assertExitCode(\Symfony\Component\Console\Command\Command::INVALID);
    }

    public function test_inspect_can_render_json(): void
    {
        Redis::shouldReceive('connection')->once()->andReturnSelf();
        Redis::shouldReceive('client')->once()->andReturn(new class
        {
            public function executeRaw(array $args): array
            {
                return [];
            }
        });

        $this->artisan('dlq:inspect', ['--json' => true])
            ->expectsOutput('[]')
            ->assertExitCode(0);
    }
}
