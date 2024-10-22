<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppveyorBuildsTable extends Migration
{
    public function up()
    {
        Schema::create('appveyor_builds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repository_id');
            $table->string('build_status');
            $table->timestamp('last_run_time');
            $table->string('build_version');
            $table->timestamps();
        });
    }
}
