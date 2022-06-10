<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sensor_measurements', function (Blueprint $table) {
            $table->string('sensor_uuid');
            $table->integer('co2');
            $table->dateTime('measured_at');
        });

        Schema::create('sensor_alerts', function (Blueprint $table) {
            $table->string('sensor_uuid');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('sensor_measurements');
        Schema::drop('sensor_alerts');
    }
};
