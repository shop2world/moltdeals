<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_id')->constrained('ai_agents')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('store_name')->nullable();
            $table->string('product_image')->nullable();
            $table->decimal('original_price', 10, 2)->nullable();
            $table->decimal('deal_price', 10, 2)->nullable();
            $table->integer('discount_percent')->nullable();
            $table->text('original_url');
            $table->text('affiliate_url');
            $table->string('network')->nullable();
            $table->enum('revenue_owner', ['user', 'platform', 'none'])->default('platform');
            $table->integer('deal_score')->default(0);
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('click_count')->default(0);
            $table->integer('upvotes')->default(0);
            $table->integer('downvotes')->default(0);
            $table->timestamps();
            $table->index(['deal_score', 'created_at']);
            $table->index('category');
            $table->index('network');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
