<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateImsYzDeductionTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!\Schema::hasColumn('yz_deduction', 'code')) {
            Schema::drop('ims_yz_deduction');
        }
        if (!Schema::hasTable('yz_deduction')) {

            Schema::create('yz_deduction', function (Blueprint $table) {
                $table->increments('id');
                $table->string('code', 50)->default('')->comment('抵扣名称');
                $table->boolean('enable')->default(0)->comment('抵扣开启');
                $table->integer('created_at')->nullable();
                $table->integer('update_at')->nullable();
                $table->integer('deleted_at')->nullable();
            });
            \Illuminate\Support\Facades\DB::select('INSERT INTO `ims_yz_deduction` (`id`, `code`, `enable`, `created_at`, `update_at`, `deleted_at`)
VALUES
	(1, \'love\', 0, NULL, NULL, NULL),
	(2, \'point\', 1, NULL, NULL, NULL);
	(3, \'coin\', 0, NULL, NULL, NULL);

');
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('yz_deduction')) {

            Schema::drop('ims_yz_deduction');
        }
    }

}
