<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateImsYzOrderAddressTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        if (!Schema::hasTable('yz_order_address')) {
            Schema::create('yz_order_address', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('order_id')->default('0');
                $table->string('address')->default('0');
            });
        }
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('yz_order_address');
	}

}
