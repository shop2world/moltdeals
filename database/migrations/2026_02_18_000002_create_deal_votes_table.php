<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('deal_votes')) {
            Schema::create('deal_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deal_id')->constrained('deals')->onDelete('cascade');
                $table->string('voter_id'); 
                $table->enum('vote_type', ['up', 'down']);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('deal_votes');
    }
};