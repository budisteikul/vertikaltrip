<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFinTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fin_transactions', function (Blueprint $table) {
            $table->id();
			
			$table->foreignId('category_id');
            $table->foreign('category_id')
                ->references('id')->on('fin_categories')
                ->onDelete('cascade')->onUpdate('cascade');
			/*
            $table->uuid('category_id');
			$table->foreign('category_id')
      			->references('id')->on('fin_categories')
      			->onDelete('cascade')->onUpdate('cascade');
            */
			$table->date('date');
			$table->float('amount', 8, 2);
			$table->tinyInteger('status')->default(0);
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
		Schema::table('fin_transactions', function (Blueprint $table) {
            $table->dropForeign('fin_transactions_category_id_foreign');
        });
        Schema::dropIfExists('fin_transactions');
    }
}
