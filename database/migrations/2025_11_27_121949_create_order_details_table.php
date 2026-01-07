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
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_id');   // Not Null
            $table->unsignedInteger('product_id'); // Not Null
            $table->unsignedBigInteger('product_attribute_id')->nullable();
            $table->decimal('price', 10, 2);       // Not Null
            $table->unsignedInteger('qty');        // Not Null
            $table->decimal('amount', 10, 2);      // Not Null
            $table->decimal('discount', 10, 2);    // Not Null
            $table->timestamps();                  // created_at & updated_at tự động
        });
    }    /**
     * Reverse the migrations.
     */
    
    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
