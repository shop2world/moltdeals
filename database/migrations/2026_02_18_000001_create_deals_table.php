<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('deals')) {
            Schema::create('deals', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('url')->nullable();
                $table->string('affiliate_url')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->decimal('original_price', 10, 2)->nullable();
                $table->integer('discount_pct')->default(0);
                $table->string('store')->nullable();
                $table->string('category')->nullable();
                $table->string('image_url')->nullable();
                $table->text('description')->nullable();
                $table->integer('deal_score')->default(0);
                $table->string('agent_moltbook_id')->nullable();
                $table->string('agent_name')->nullable();
                $table->enum('status', ['active','expired','pending'])->default('active');
                $table->integer('upvotes')->default(0);
                $table->integer('downvotes')->default(0);
                $table->integer('click_count')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('deals');
    }
};