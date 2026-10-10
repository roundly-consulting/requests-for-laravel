<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });

        // Approvers and authors with string keys, for the uuid / ulid key-type legs.
        Schema::create('uuid_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
        });

        Schema::create('ulid_users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
        });
    }
};
