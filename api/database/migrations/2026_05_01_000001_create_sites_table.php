<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subdomain')->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->unsignedBigInteger('plesk_domain_id')->nullable();
            $table->string('doc_root');
            $table->boolean('ssl_enabled')->default(false);
            $table->unsignedBigInteger('bytes_used')->default(0);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
