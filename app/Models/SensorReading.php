<?php

namespace App\Models;

use App\Services\Alerts\AlertService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SensorReading extends Model
{
    use HasFactory;

    protected $fillable = ['sensor_id', 'value', 'reading_time'];
    protected $casts = [
        'reading_time' => 'datetime',
    ];

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
        return app(AlertService::class)->triggeredRulesForReading($this);
    }

    public function checkForAlert(): Collection
    {
        return app(AlertService::class)->createAlertsForReading($this);
    }
}
