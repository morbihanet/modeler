<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Kv extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        Schema::create('kv', function (Blueprint $table) {
            $table->string('k')->primary()->unique();
            $table->longText('v')->nullable();
            $table->unsignedBigInteger('e')->index()->default(0);
            $table->timestamp('called_at')->nullable()->useCurrent();
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kv');
    }
}
