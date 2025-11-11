<?php

namespace App\Console\Commands;

use App\Models\Sensor;
use App\Models\SensorReading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeFutureSensorReadings extends Command
{
    protected $signature = 'sensors:purge-future {--dry-run : Only display the readings that would be deleted}';

    protected $description = 'Delete sensor readings whose timestamp is ahead of the current time.';

    public function handle(): int
    {
        $referenceTime = now();

        $futureReadings = SensorReading::select('sensor_id', DB::raw('COUNT(*) as total'))
            ->where('reading_time', '>', $referenceTime)
            ->groupBy('sensor_id')
            ->orderBy('sensor_id')
            ->get();

        if ($futureReadings->isEmpty()) {
            $this->info('No se encontraron lecturas en el futuro. No se realizaron cambios.');
            return self::SUCCESS;
        }

        $this->warn('Se detectaron lecturas con horario en el futuro:');
        $this->table(
            ['Sensor ID', 'Nombre', 'Total lecturas futuras', 'Primer registro', 'Último registro'],
            $futureReadings->map(function ($row) use ($referenceTime) {
                $sensor = Sensor::find($row->sensor_id);

                $first = SensorReading::where('sensor_id', $row->sensor_id)
                    ->where('reading_time', '>', $referenceTime)
                    ->orderBy('reading_time')
                    ->first();

                $last = SensorReading::where('sensor_id', $row->sensor_id)
                    ->where('reading_time', '>', $referenceTime)
                    ->orderByDesc('reading_time')
                    ->first();

                return [
                    $row->sensor_id,
                    $sensor?->name ?? 'Desconocido',
                    $row->total,
                    optional($first?->reading_time)->format('Y-m-d H:i:s') ?? 'N/A',
                    optional($last?->reading_time)->format('Y-m-d H:i:s') ?? 'N/A',
                ];
            })
        );

        if ($this->option('dry-run')) {
            $this->comment('Modo simulación activado (dry-run). No se eliminaron lecturas.');
            return self::SUCCESS;
        }

        $totalDeleted = SensorReading::where('reading_time', '>', $referenceTime)->delete();
        $this->info("Lecturas eliminadas: {$totalDeleted}");

        return self::SUCCESS;
    }
}
