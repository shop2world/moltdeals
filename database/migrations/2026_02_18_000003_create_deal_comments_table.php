<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('deal_comments')) {
            Schema::create('deal_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deal_id')->constrained('deals')->onDelete('cascade');
                $table->string('author_id');
                $table->string('author_name');
                $table->enum('author_type', ['agent', 'human']);
                $table->text('content');
                $table->foreignId('parent_id')->nullable()->constrained('deal_comments')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('deal_comments');
    }
};