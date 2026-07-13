<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->connection())->create('sms_provider_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32)->unique();
            $table->unsignedBigInteger('success_count')->default(0);
            $table->unsignedBigInteger('failure_count')->default(0);
            $table->unsignedBigInteger('total_latency_ms')->default(0);
            $table->unsignedInteger('avg_latency_ms')->default(0);
            $table->decimal('score', 6, 4)->default(0);
            $table->string('circuit_state', 16)->default('closed');
            $table->timestamp('circuit_opened_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('half_open_probes')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('window_start')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists('sms_provider_metrics');
    }

    private function connection(): ?string
    {
        return config('sms-switch.logging.connection');
    }
};
