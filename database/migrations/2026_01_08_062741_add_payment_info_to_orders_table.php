<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('orders', function (Blueprint $table) {
        // Kiểm tra nếu chưa có thì mới thêm
        if (!Schema::hasColumn('orders', 'total_money')) {
            $table->decimal('total_money', 15, 2)->default(0)->after('status');
        }
        if (!Schema::hasColumn('orders', 'payment_method')) {
            $table->string('payment_method')->default('cod')->after('status');
        }
    });
}

public function down()
{
    Schema::table('orders', function (Blueprint $table) {
        $table->dropColumn(['total_money', 'payment_method']);
    });
}
};
