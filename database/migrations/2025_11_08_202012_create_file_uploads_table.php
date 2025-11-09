<?php

use App\Enums\FileStatusEnums;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_name');
            $table->enum('status', FileStatusEnums::values())->default(FileStatusEnums::PENDING->value);
            $table->integer('processed_rows')->default(0);
            $table->integer('total_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('file_uploads');
    }
};
