<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserved_subdomains', function (Blueprint $table) {
            $table->string('subdomain')->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserved_subdomains');
    }
};
