<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Requests\Enums\Status;

return new class extends Migration
{
    public function up(): void
    {
        // The requests' own id, which the approvals engine's subject / approvable morph
        // columns point at, and the author morph's key type — two separate axes.
        $primaryKeyType = KeyType::fromConfig('requests.primary_key_type');
        $keyType = KeyType::fromConfig('requests.key_type');

        Schema::create('requests', function (Blueprint $table) use ($primaryKeyType, $keyType): void {
            match ($primaryKeyType) {
                KeyType::BigInt => $table->id(),
                KeyType::Uuid => $table->uuid('id')->primary(),
                KeyType::Ulid => $table->ulid('id')->primary(),
            };

            $table->string('status', 16)->default(Status::New->value);
            $table->morphKey('author', $keyType, nullable: true);
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
