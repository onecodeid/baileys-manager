<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsAppSessionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('whats_app_sessions', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('phone_number')->nullable();
    $table->string('status')->default('disconnected'); // connected, disconnected, qr
    $table->text('qr_code')->nullable();
    $table->json('session_data')->nullable();
    $table->timestamp('last_active')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('whats_app_sessions');
    }
}
