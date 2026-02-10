<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // CHANGE THIS LINE:
            // $table->id();

            // TO THIS (Allows UUID strings):
            $table->uuid('id')->primary();

            $table->string('firstName');
            $table->string('lastName');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->string('phone')->unique(); // Ensure 255 chars is enough, or use ->text()
            $table->timestamps();
        });

        // ... rest of your code
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
