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
        Schema::create('product_sales', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Not Null
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade'); // Not Null
            $table->decimal('price_sale', 10, 2); // Not Null
            $table->dateTime('date_begin'); // Not Null
            $table->dateTime('date_end'); // Not Null
            $table->unsignedInteger('created_by')->default(1);
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps(); // created_at & updated_at tự động
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sales');
    }
};
