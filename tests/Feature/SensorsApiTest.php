<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SensorsApiTest extends TestCase
{
    private const API_URL = '/api/v1/sensors';

    public function testGettingDataOfUnknownSensor()
    {
        $sensorUuid = Str::random();

        $response = $this->get(self::API_URL . '/' . $sensorUuid);
        $response->assertStatus(404);

        $response = $this->get(self::API_URL . '/' . $sensorUuid . '/metrics');
        $response->assertStatus(404);

        $response = $this->get(self::API_URL . '/' . $sensorUuid . '/alerts');
        $response->assertStatus(404);
    }

    public function testGettingStatusOfKnownSensor()
    {
        $sensorUuid = Str::random();
        $response = $this->sendMeasurements($sensorUuid, 2000, Carbon::now());

        $response = $this->get(self::API_URL . '/' . $sensorUuid);
        $response->assertStatus(200);
        $response->assertExactJson(['status' => 'OK']);

        $response = $this->get(self::API_URL . '/' . $sensorUuid . '/metrics');
        $response->assertStatus(200);
        $response->assertExactJson([
            'maxLast30Days' => 2000,
            'avgLast30Days' => 2000,
        ]);

        $response = $this->get(self::API_URL . '/' . $sensorUuid . '/alerts');
        $response->assertStatus(200);
        $response->assertExactJson([]);
    }

    public function testRateLimiting()
    {
        $this->markTestIncomplete('This test is incomplete due to the ambiguous requirements to the rate limiting');

        $now = Carbon::now();
        Carbon::setTestNow($now);

        $sensorUuid = Str::random();
        $response = $this->sendMeasurements($sensorUuid, 2000, $now);
        $response->assertStatus(Response::HTTP_OK);

        $response = $this->sendMeasurements($sensorUuid, 2000, $now);
        $response->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        $response = $this->sendMeasurements($sensorUuid, 2000, $now);
        $response->assertStatus(Response::HTTP_OK);

        Carbon::setTestNow($now->addSeconds(59));
        $response = $this->sendMeasurements($sensorUuid, 2000, $now);
        $response->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        Carbon::setTestNow($now->addSeconds(60));
        $response = $this->sendMeasurements($sensorUuid, 2000, $now);
        $response->assertStatus(Response::HTTP_OK);
    }

    public function testSensorStatusTransitions()
    {
        $sensorUuid = Str::random();
        $now = Carbon::now()->subMinutes(60);

        // When: 1 safe measurement
        $this->sendMeasurements($sensorUuid, 2000, $now->addMinute());

        // Then: status is OK
        $response = $this->get(self::API_URL . '/' . $sensorUuid);
        $response->assertExactJson(['status' => 'OK']);

        // When: there is 1 measurement above the threshold
        $this->sendMeasurements($sensorUuid, 2001, $now->addMinute());
        // Then: status is warning
        $response = $this->get(self::API_URL . '/' . $sensorUuid);
        $response->assertExactJson(['status' => 'WARN']);

        // When: there are 3 measurements above the threshold
        $this->sendMeasurements($sensorUuid, 2001, $now->addMinute());
        $this->sendMeasurements($sensorUuid, 2001, $now->addMinute());
        // Then: status is alert
        $response = $this->get(self::API_URL . '/' . $sensorUuid);
        $response->assertExactJson(['status' => 'ALERT']);

        // When: there are 2 safe measurements after the alert status
        $this->sendMeasurements($sensorUuid, 2000, $now->addMinute());
        $this->sendMeasurements($sensorUuid, 2000, $now->addMinute());
        // Then: status is still alert
        $response = $this->get(self::API_URL . '/' . $sensorUuid);
        $response->assertExactJson(['status' => 'ALERT']);

        // When: 3rd in a row safe measurement
        $this->sendMeasurements($sensorUuid, 2000, $now->addMinute());
        // Then: status is reset to OK
        $response = $this->get(self::API_URL . '/' . $sensorUuid);
        $response->assertExactJson(['status' => 'OK']);
    }

    public function testGettingSensorAlerts()
    {
        $sensorUuid = Str::random();
        $now = Carbon::now()->subMinutes(60);
        $expectedTimeRanges = [];

        for ($j = 1; $j <= 3; $j++) {
            $expectedTimeRanges[$j]['start'] = $now->clone()->addMinute();

            for ($i = 1; $i <= 3; $i++) {
                $this->sendMeasurements($sensorUuid, 2000 + $i * $j, $now->addMinute());
            }

            $expectedTimeRanges[$j]['end'] = $now->clone()->addMinute();

            for ($i = 1; $i <= 3; $i++) {
                $this->sendMeasurements($sensorUuid, 2000 - $i * $j, $now->addMinute());
            }
        }

        $response = $this->get(self::API_URL . '/' . $sensorUuid . '/alerts');
        $response->assertStatus(200);

        $response->assertExactJson([
            [
                'startTime' => $expectedTimeRanges[1]['start'],
                'endTime' => $expectedTimeRanges[1]['end'],
                'measurement1' => 2001,
                'measurement2' => 2002,
                'measurement3' => 2003,
            ],
            [
                'startTime' => $expectedTimeRanges[2]['start'],
                'endTime' => $expectedTimeRanges[2]['end'],
                'measurement1' => 2002,
                'measurement2' => 2004,
                'measurement3' => 2006,
            ],
            [
                'startTime' => $expectedTimeRanges[3]['start'],
                'endTime' => $expectedTimeRanges[3]['end'],
                'measurement1' => 2003,
                'measurement2' => 2006,
                'measurement3' => 2009,
            ],
        ]);
    }

    public function testGettingMetrics()
    {
        $sensorUuid = Str::random();
        $now = Carbon::now();
        Carbon::setTestNow($now);

        $now = $now->clone();

        // When: there are 2 measurements in the last 30 days
        $this->sendMeasurements($sensorUuid, 2000, $now);
        $this->sendMeasurements($sensorUuid, 2200, $now->subSeconds(30 * 24 * 3600));

        // Then: they are accounted in the statistics calculation
        $response = $this->get(self::API_URL . '/' . $sensorUuid . '/metrics');
        $response->assertStatus(200);
        $response->assertExactJson([
            'maxLast30Days' => 2200,
            'avgLast30Days' => 2100,
        ]);

        // When: there is one measurement at least 1 second older than 30 days
        $this->sendMeasurements($sensorUuid, 2300, $now->subSecond());
        // Then: it's not accounted in the statistics
        $response = $this->get(self::API_URL . '/' . $sensorUuid . '/metrics');
        $response->assertStatus(200);
        $response->assertExactJson([
            'maxLast30Days' => 2200,
            'avgLast30Days' => 2100,
        ]);
    }

    private function sendMeasurements(string $sensorUuid, int $co2Level, Carbon $time): TestResponse
    {
        return $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => $co2Level,
            'time' => $time,
        ]);
    }
}
