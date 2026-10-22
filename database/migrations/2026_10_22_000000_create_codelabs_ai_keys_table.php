<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codelabs_ai_keys', function (Blueprint $table) {
            $table->id();
            $table->string('connection')->unique();
            // Encrypted with the app key; never stored or returned in plain text.
            $table->text('key')->nullable();
            // Null means "follow config('codelabs-ai.enabled')".
            $table->boolean('enabled')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codelabs_ai_keys');
    }
};
