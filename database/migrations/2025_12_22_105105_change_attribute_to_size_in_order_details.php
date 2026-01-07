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
    Schema::table('order_details', function (Blueprint $table) {
        // 1. Xóa cột ID cũ đi
        $table->dropColumn('product_attribute_id');
        
        // 2. Thêm cột mới tên là 'size' kiểu String để chứa "S", "M", "L"
        $table->string('size', 50)->nullable()->after('product_id');
    });
}

    /**
     * Reverse the migrations.
     */
   public function down(): void
{
    Schema::table('order_details', function (Blueprint $table) {
        $table->dropColumn('size');
        $table->unsignedBigInteger('product_attribute_id')->nullable();
    });
}
};
