<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFileTempsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('file_temps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade')->onUpdate('cascade');

            $table->string('file')->nullable();
            $table->string('key')->nullable();

            $table->timestamps(6);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('file_temps');
    }
}
