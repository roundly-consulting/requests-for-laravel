<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Requests\Enums\Status;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 16)->default(Status::New->value);
            $table->nullableMorphs('author');
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('meta')->nullable();
            $table->jsonb('require_approvals_from')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
