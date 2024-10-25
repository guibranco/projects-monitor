<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCodeclimateMetricsTable extends Migration
{
    public function up()
    {
        Schema::create('codeclimate_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repository_id');
            $table->decimal('gpa', 3, 2)->nullable();
            $table->integer('issues_count')->nullable();
            $table->decimal('maintainability_index', 5, 2)->nullable();
            $table->timestamp('last_updated')->useCurrent();

            $table->foreign('repository_id')->references('id')->on('repositories');
        });
    }

    public function down()
    {
        Schema::dropIfExists('codeclimate_metrics');
    }
}
