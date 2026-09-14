<?php

namespace Tests\Feature;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Mockery;
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
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('client')->once()->andReturn(new class
        {
            public function executeRaw(array $args): array
            {
                return [];
            }
        });
        Redis::shouldReceive('connection')->with('default')->once()->andReturn($connection);

        $this->artisan('dlq:inspect', ['--json' => true])
            ->expectsOutput('[]')
            ->assertExitCode(0);
    }
}
