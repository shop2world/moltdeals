<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->onDelete('cascade');
            $table->string('network')->nullable();
            $table->enum('revenue_owner', ['user', 'platform', 'none']);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referer')->nullable();
            $table->timestamp('clicked_at')->useCurrent();
            $table->index(['deal_id', 'clicked_at']);
            $table->index('revenue_owner');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('clicks');
    }
};
