<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        // Throws for an unrecognized value, so a typo in the host's config fails
        // the migration loudly instead of creating the wrong column type.
        $keyType = KeyType::fromConfig('attributes.key_type');

        Schema::create($this->table(), function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->morphKey('owner', $keyType, nullable: true);
            $table->string('name');
            $table->text('value')->nullable();
            $table->string('value_type')->nullable()->default(AttributeType::String_->value);
            $table->boolean('is_encrypted')->default(false);
            // Deterministic hash of a value under a `unique` definition (keyed for
            // encrypted values); null otherwise. The unique index makes uniqueness a
            // database guarantee instead of a read-then-write check.
            $table->string('unique_hash', 64)->nullable()->unique();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // One row per owner + name — soft-deleted rows included: re-attaching a
            // detached name restores its row.
            $table->unique(['owner_type', 'owner_id', 'name']);
        });
    }

    private function table(): string
    {
        $table = config('attributes.table', 'attributes');

        return is_string($table) ? $table : 'attributes';
    }
};
