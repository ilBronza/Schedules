<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table(config('schedules.models.type.table'), function (Blueprint $table) {
            $table->float('percentage_validity')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(config('schedules.models.type.table'), function (Blueprint $table) {
            $table->dropColumn('percentage_validity');
        });
    }
};