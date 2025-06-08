<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
			
			$table->string('name');
            $table->string('slug');
			
			$table->bigInteger('category_id')->default(0);
			$table->bigInteger('bokun_id')->default(0);
			
            $table->float('min_participant', precision: 53)->default(1);

            $table->boolean('deposit_percentage')->default(true);
            $table->float('deposit_amount', precision: 53)->default(0);

            $table->tinyInteger('status')->default(1);
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
        Schema::dropIfExists('products');
    }
}
