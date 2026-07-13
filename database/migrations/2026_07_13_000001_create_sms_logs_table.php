<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->connection())->create('sms_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32)->index();
            $table->string('type', 16);
            $table->string('to', 32)->index();
            $table->text('body')->nullable();
            $table->string('pattern', 128)->nullable();
            $table->json('tokens')->nullable();
            $table->string('status', 16);
            $table->string('message_id', 128)->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->text('error_message')->nullable();
            $table->uuid('correlation_id')->index();
            $table->timestamps();

            $table->index(['provider', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists('sms_logs');
    }

    private function connection(): ?string
    {
        return config('sms-switch.logging.connection');
    }
};
