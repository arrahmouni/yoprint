<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->string('unique_key')->primary();
            $table->string('product_title');
            $table->text('product_description')->nullable();
            $table->string('style_number')->nullable();
            $table->string('sanmar_mainframe_color')->nullable();
            $table->string('size')->nullable();
            $table->string('color_name')->nullable();
            $table->decimal('piece_price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
