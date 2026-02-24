<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')
                ->constrained('messages')
                ->restrictOnDelete();
            $table->string('channel');                         // email|slack|sms
            $table->string('status')->default('pending');      // pending|success|failed
            $table->json('payload')->nullable();               // payload enviado al canal
            $table->text('response')->nullable();              // respuesta del canal
            $table->text('error_message')->nullable();         // detalle del error si falla
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['message_id', 'channel']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_logs');
    }
};
