<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SensorReading extends Model
{
    use HasFactory;

    protected $fillable = ['sensor_id', 'value', 'reading_time'];

    public function sensor()
    {
        return $this->belongsTo(Sensor::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Devuelve las reglas que se disparan con la lectura actual.
     */
    public function triggeredAlertRules(): Collection
    {
        $sensor = $this->sensor()->with(['device.classroom'])->first();

        if (!$sensor) {
            return collect();
        }

        $alertRules = AlertRule::query()
            ->where('sensor_type_id', $sensor->sensor_type_id)
            ->where(function ($query) use ($sensor) {
                $query->whereNull('device_id')
                    ->orWhere('device_id', $sensor->device_id);
            })
            ->where(function ($query) use ($sensor) {
                $query->whereNull('sensor_id')
                    ->orWhere('sensor_id', $sensor->id);
            })
            ->get();

        return $alertRules->filter(function ($alertRule) {
            $minDefined = is_numeric($alertRule->min_value);
            $maxDefined = is_numeric($alertRule->max_value);

            $belowMin = $minDefined && $this->value <= $alertRule->min_value;
            $aboveMax = $maxDefined && $this->value >= $alertRule->max_value;

            return $belowMin || $aboveMax;
        })->values();
    }

    public function checkForAlert(): Collection
    {
        $triggeredRules = $this->triggeredAlertRules();

        foreach ($triggeredRules as $alertRule) {
            $alreadyExists = Alert::where('sensor_reading_id', $this->id)
                ->where('alert_rule_id', $alertRule->id)
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            Alert::create([
                'sensor_reading_id' => $this->id,
                'alert_rule_id' => $alertRule->id,
                'resolved' => false,
            ]);
        }

        return $triggeredRules;
    }
}
