<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChangeEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('change_emails', function (Blueprint $table) {
            $table->id();
			$table->foreignId('user_id');
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade')->onUpdate('cascade');
			$table->string('email');
			$table->string('token');
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
        Schema::dropIfExists('change_emails');
    }
}
