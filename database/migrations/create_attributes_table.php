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
        // Silently falls back to bigint for an unrecognized value, so a typo in
        // the host's config never leaves the package unable to migrate.
        $keyType = KeyType::fromConfig('attributes.key_type');

        Schema::create($this->table(), function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->morphKey('owner', $keyType, nullable: true);
            $table->string('name');
            $table->text('value')->nullable();
            $table->string('value_type')->nullable()->default(AttributeType::String_->value);
            $table->boolean('is_encrypted')->default(false)->after('value_type');
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_type', 'owner_id', 'name']);
        });
    }

    private function table(): string
    {
        $table = config('attributes.table', 'attributes');

        return is_string($table) ? $table : 'attributes';
    }
};
