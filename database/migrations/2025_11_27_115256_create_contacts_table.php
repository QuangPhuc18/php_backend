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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id(); // id unsignedBigInteger AUTO_INCREMENT
            $table->unsignedInteger('user_id')->nullable(); // Null
            $table->string('name'); // Not Null
            $table->string('email'); // Not Null
            $table->string('phone'); // Not Null
            $table->mediumText('content'); // Not Null
            $table->unsignedInteger('reply_id')->default(0);
            $table->unsignedInteger('created_by')->default(1);
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps(); // tự thêm created_at và updated_at

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
