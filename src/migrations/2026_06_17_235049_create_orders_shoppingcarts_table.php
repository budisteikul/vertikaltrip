<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders_shoppingcarts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id');
            $table->foreign('order_id')
                  ->references('id')->on('orders')
                  ->onDelete('cascade')->onUpdate('cascade');

            $table->foreignId('shoppingcart_id');
            $table->foreign('shoppingcart_id')
                  ->references('id')->on('shoppingcarts')
                  ->onDelete('cascade')->onUpdate('cascade');

            $table->timestamps(6);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders_shoppingcarts');
    }
};
