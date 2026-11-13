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
        Schema::create('counter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unsignedBigInteger('counter_value');
            $table->dateTime('observed_at');
            $table->dateTime('received_at');
            $table->string('source', 50);
            $table->timestamps();

            $table->index(['machine_id', 'observed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counter_readings');
    }
};
