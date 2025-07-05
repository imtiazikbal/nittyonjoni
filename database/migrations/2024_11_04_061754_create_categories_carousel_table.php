<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories_carousel', function (Blueprint $table) {
            $table->id();
            $table->string('isVideo')->default(0); // 1 or 0
            $table->string(column: 'src');
            $table->string(column: 'title');

            $table->unsignedBigInteger('categoryId');
            $table->foreign('categoryId')->references('id')->on('categories')->onDelete('cascade');
            $table->unsignedBigInteger('subCategoryId');
            $table->foreign('subCategoryId')->references('id')->on('sub_categories')->onDelete('cascade');
            $table->string('status')->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories_carousel');
    }
};
