<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id(); // id unsignedBigInteger AUTO_INCREMENT
            $table->string('name'); // Not Null
            $table->string('image'); // Not Null
            $table->string('link')->nullable(); // Null
            $table->enum('position', ['slideshow', 'ads'])->default('slideshow'); // Default 'slideshow'
            $table->unsignedInteger('sort_order')->default(0);
            $table->tinyText('description')->nullable();
            $table->dateTime('created_at'); // Not Null
            $table->unsignedInteger('created_by')->default(1);
            $table->dateTime('updated_at')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
