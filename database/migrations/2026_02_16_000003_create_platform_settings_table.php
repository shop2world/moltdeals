<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
        DB::table('platform_settings')->insert([
            ['key' => 'affiliate_linkshare_id', 'value' => null, 'description' => 'Rakuten LinkShare Affiliate ID', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'affiliate_amazon_tag', 'value' => null, 'description' => 'Amazon Associates Tag', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'affiliate_cj_id', 'value' => null, 'description' => 'CJ Affiliate Publisher ID', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'affiliate_ebay_id', 'value' => null, 'description' => 'eBay Partner Network ID', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'affiliate_shareasale_id','value' => null, 'description' => 'ShareASale Affiliate ID', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
