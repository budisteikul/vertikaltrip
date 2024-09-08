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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contact_id');
            $table->foreign('contact_id')
                  ->references('id')->on('contacts')
                  ->onDelete('cascade')->onUpdate('cascade');
            
            $table->string('type')->nullable();
            $table->string('from')->nullable();
            $table->string('to')->nullable();

            $table->string('message_id')->nullable();
            $table->text('context')->nullable();

            $table->text('text')->nullable();
            $table->text('image')->nullable();
            $table->text('template')->nullable();
            $table->text('reaction')->nullable();
            $table->text('interactive')->nullable();
            $table->text('order')->nullable();

            $table->string('status')->nullable();
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
        Schema::dropIfExists('messages');
    }
};
