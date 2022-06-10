<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SensorsController extends Controller
{
    public function postMeasurements()
    {
        return new Response('posted');
    }

    public function getStatus()
    {
        return new Response('status');
    }

    public function getAlerts()
    {
        return new Response('alerts');
    }

    public function getMetrics()
    {
        return new Response('metrics');
    }
}
