<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Str;
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
        $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2000,
            'time' => '2019-02-01T18:55:47+00:00',
        ]);

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
        $response = $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2000,
            'time' => '2019-02-01T18:55:47+00:00',
        ]);
        $response->assertStatus(Response::HTTP_OK);

        $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2000,
            'time' => '2019-02-01T18:55:47+00:00',
        ]);
        $response->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        $this->postJson(self::API_URL . '/' . $sensorUuid . '-another/measurements', [
            'co2' => 2000,
            'time' => '2019-02-01T18:55:47+00:00',
        ]);
        $response->assertStatus(Response::HTTP_OK);

        Carbon::setTestNow($now->addSeconds(59));
        $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2000,
            'time' => '2019-02-01T18:55:47+00:00',
        ]);
        $response->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        Carbon::setTestNow($now->addSeconds(60));
        $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2000,
            'time' => '2019-02-01T18:55:47+00:00',
        ]);
        $response->assertStatus(Response::HTTP_OK);
    }

    public function testSensorStatusTransitions()
    {
        $sensorUuid = Str::random();
        $response = $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2000,
            'time' => '2019-02-01T18:55:47+00:00',
        ]);

        $response = $this->get(self::API_URL . '/' . $sensorUuid);
        $response->assertExactJson(['status' => 'OK']);

        $response = $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2001,
            'time' => '2019-02-01T18:55:47+00:00',
        ]);
        $response = $this->get(self::API_URL . '/' . $sensorUuid);
        $response->assertExactJson(['status' => 'WARN']);

        $response = $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2001,
            'time' => '2019-02-01T18:55:47+00:00',
        ]);
        $response = $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2001,
            'time' => '2019-02-01T18:55:47+00:00',
        ]);
        $response = $this->get(self::API_URL . '/' . $sensorUuid);
        $response->assertExactJson(['status' => 'ALERT']);

        $response = $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2001,
            'time' => '2019-02-01T18:55:47+00:00',
        ]);
        $response = $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2001,
            'time' => '2019-02-01T18:55:47+00:00',
        ]);
        $response = $this->get(self::API_URL . '/' . $sensorUuid);
        $response->assertExactJson(['status' => 'ALERT']);

        $response = $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2001,
            'time' => '2019-02-01T18:55:47+00:00',
        ]);
        $response = $this->get(self::API_URL . '/' . $sensorUuid);
        $response->assertExactJson(['status' => 'OK']);
    }

    public function testGettingSensorAlerts()
    {
        $sensorUuid = Str::random();

        for ($j = 1; $j <= 3; $j++) {
            for ($i = 1; $i <= 3; $i++) {
                $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
                    'co2' => 2000 + $i * $j,
                    'time' => '2019-02-01T18:55:47+00:00',
                ]);
            }
            for ($i = 1; $i <= 3; $i++) {
                $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
                    'co2' => 2000 - $i * $j,
                    'time' => '2019-02-01T18:55:47+00:00',
                ]);
            }
        }

        $response = $this->get(self::API_URL . '/' . $sensorUuid . '/alerts');
        $response->assertStatus(200);
        $response->assertExactJson([
            [
                'startTime' => '2019-02-01T18:55:47+00:00',
                'endTime' => '2019-02-01T18:55:47+00:00',
                'measurement1' => 2001,
                'measurement1' => 2002,
                'measurement1' => 2003,
            ],
            [
                'startTime' => '2019-02-01T18:55:47+00:00',
                'endTime' => '2019-02-01T18:55:47+00:00',
                'measurement1' => 2002,
                'measurement1' => 2004,
                'measurement1' => 2006,
            ],
            [
                'startTime' => '2019-02-01T18:55:47+00:00',
                'endTime' => '2019-02-01T18:55:47+00:00',
                'measurement1' => 2003,
                'measurement1' => 2006,
                'measurement1' => 2009,
            ],
        ]);
    }

    public function testGettingMetrics()
    {
        $sensorUuid = Str::random();
        $now = Carbon::now();
        Carbon::setTestNow($now);

        $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2000,
            'time' => $now->toIso8601String(),
        ]);
        $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2200,
            'time' => $now->subSeconds(30 * 24 * 3600)->toIso8601String(),
        ]);

        $response = $this->get(self::API_URL . '/' . $sensorUuid . '/metrics');
        $response->assertStatus(200);
        $response->assertExactJson([
            'maxLast30Days' => 2200,
            'avgLast30Days' => 2100,
        ]);

        $this->postJson(self::API_URL . '/' . $sensorUuid . '/measurements', [
            'co2' => 2300,
            'time' => $now->subSeconds(1)->toIso8601String(),
        ]);
        $response = $this->get(self::API_URL . '/' . $sensorUuid . '/metrics');
        $response->assertStatus(200);
        $response->assertExactJson([
            'maxLast30Days' => 2200,
            'avgLast30Days' => 2100,
        ]);
    }
}
