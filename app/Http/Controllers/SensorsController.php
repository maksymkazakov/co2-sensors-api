<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SensorsController extends Controller
{
    public const SAFE_CO2_THRESHOLD = 2000;

    public const STATUS_ALERT = 'ALERT';
    public const STATUS_OK = 'OK';
    public const STATUS_WARNING = 'WARN';

    public function postMeasurements(Request $request, string $uuid)
    {
        DB::table('sensor_measurements')
            ->insert([
                'sensor_uuid' => $uuid,
                'co2' => $request->get('co2'),
                'measured_at' => $request->get('time'),
            ]);

        // TODO: use event-based approach
        $status = $this->detectStatus($uuid);
        if ($status === self::STATUS_ALERT && !$this->hasOpenAlert($uuid)) {
            DB::table('sensor_alerts')
                ->insert([
                    'sensor_uuid' => $uuid,
                    'started_at' => DB::table('sensor_measurements')
                        ->where('sensor_uuid', $uuid)
                        ->where('measured_at', '<=', $request->get('time'))
                        ->orderByDesc('measured_at')
                        ->offset(2)
                        ->limit(1)
                        ->first()
                        ->measured_at,
                ]);
        }
        if ($status === self::STATUS_OK && $this->hasOpenAlert($uuid)) {
            DB::table('sensor_alerts')
                ->where('sensor_uuid', $uuid)
                ->whereNull('ended_at')
                ->update([
                    'ended_at' => DB::table('sensor_measurements')
                        ->where('sensor_uuid', $uuid)
                        ->where('measured_at', '<=', $request->get('time'))
                        ->orderByDesc('measured_at')
                        ->offset(2)
                        ->limit(1)
                        ->first()
                        ->measured_at,
                ]);
        }
    }

    public function getStatus(string $uuid)
    {
        if ($this->hasOpenAlert($uuid)) {
            return new JsonResponse(['status' => self::STATUS_ALERT]);
        }

        return new JsonResponse(['status' => $this->detectStatus($uuid)]);
    }

    public function getAlerts(string $uuid)
    {
        $hasMeasurements = DB::table('sensor_measurements')
            ->where('sensor_uuid', $uuid)
            ->count() > 0;
        if (!$hasMeasurements) {
            throw new NotFoundHttpException();
        }

        $payload = [];

        $alerts = DB::table('sensor_alerts')
            ->where('sensor_uuid', $uuid)
            ->orderBy('started_at')
            ->get();

        foreach ($alerts as $alert) {
            $measurements = DB::table('sensor_measurements')
                ->where('sensor_uuid', $uuid)
                ->where('measured_at', '>=', $alert->started_at)
                ->orderBy('measured_at')
                ->limit(3)
                ->get();

            $payload[] = [
                'startTime' => $alert->started_at,
                'endTime' => $alert->ended_at,
                'measurement1' => $measurements[0]->co2,
                'measurement2' => $measurements[1]->co2,
                'measurement3' => $measurements[2]->co2,
            ];
        }

        return new JsonResponse($payload);
    }

    public function getMetrics(string $uuid)
    {
        $hasMeasurements = DB::table('sensor_measurements')
            ->where('sensor_uuid', $uuid)
            ->count() > 0;
        if (!$hasMeasurements) {
            throw new NotFoundHttpException();
        }

        $aggregatedData = DB::table('sensor_measurements')
            ->selectRaw('AVG(co2) as avg_co2, MAX(co2) as max_co2')
            ->where('sensor_uuid', $uuid)
            ->where('measured_at', '>', Carbon::now()->subSeconds(30 * 24 * 3600)->toIso8601String())
            ->groupBy('sensor_uuid')
            ->first();

        return new JsonResponse([
            'maxLast30Days' => $aggregatedData->max_co2 ?? 0,
            'avgLast30Days' => $aggregatedData->avg_co2 ?? 0,
        ]);
    }

    private function hasOpenAlert(string $uuid): bool
    {
        return DB::table('sensor_alerts')
            ->where('sensor_uuid', $uuid)
            ->whereNull('ended_at')
            ->count() > 0;
    }

    private function detectStatus(string $uuid): string
    {
        $latestMeasurements = DB::table('sensor_measurements')
            ->where('sensor_uuid', $uuid)
            ->orderBy('measured_at', 'desc')
            ->limit(3)
            ->get();

        $measurementsAboveThreshold = $latestMeasurements->reduce(
            function($carry, $row) {
                return $carry + (int) ($row->co2 > self::SAFE_CO2_THRESHOLD);
            },
            0
        );

        switch (true) {
            case $latestMeasurements->count() === 0:
                throw new NotFoundHttpException();

            case $measurementsAboveThreshold === 3:
                return self::STATUS_ALERT;

            case $measurementsAboveThreshold > 0:
                return self::STATUS_WARNING;

            case $measurementsAboveThreshold === 0:
                default:
                return self::STATUS_OK;
        }
    }
}
