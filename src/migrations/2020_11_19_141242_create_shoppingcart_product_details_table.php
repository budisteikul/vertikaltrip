<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShoppingcartProductDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shoppingcart_product_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shoppingcart_product_id');
            $table->foreign('shoppingcart_product_id')
                  ->references('id')->on('shoppingcart_products')
                  ->onDelete('cascade')->onUpdate('cascade');
            
            $table->string('type')->default('product');
                
            $table->string('title')->nullable();
            $table->string('people')->nullable();
            $table->string('qty')->nullable();
            $table->string('price')->nullable();
            $table->string('unit_price')->nullable();
            
            $table->string('currency')->default('USD');
            $table->float('subtotal', precision: 53)->default(0);
            $table->float('discount', precision: 53)->default(0);
            $table->float('tax', precision: 53)->default(0);
            $table->float('fee', precision: 53)->default(0);
            $table->float('admin', precision: 53)->default(0);
            $table->float('total', precision: 53)->default(0);

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
        Schema::dropIfExists('shoppingcart_product_details');
    }
}
