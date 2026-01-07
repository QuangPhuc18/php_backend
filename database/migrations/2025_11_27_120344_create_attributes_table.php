<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_xx_xx_xxxxxx_create_attributes_table.php

public function up()
{
    Schema::create('attributes', function (Blueprint $table) {
        $table->id(); // Tự động tạo id, primary key
        $table->string('name'); // Tên thuộc tính (VD: Size, Color)
        // Bạn có thể thêm timestamps nếu muốn, dù file thiết kế không ghi
        //$table->timestamps(); 
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
