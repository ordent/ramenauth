<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddingRamenVerification extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ramen_verifications', function($table){
            $table->increments('id');
            $table->integer('user_id');
            $table->string('code');
            $table->string('response')->nullable();
            $table->enum('verified_by', ['phone', 'email'])->default('email');
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ramen_forgottens', function($table){
            $table->increments('id');
            $table->integer('user_id');
            $table->string('code');
            $table->string('response')->nullable();
            $table->enum('remember_by', ['phone', 'email'])->default('email');
            $table->dateTime('remember_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('ramen_verifications');
        Schema::drop('ramen_forgottens');
    }
}
