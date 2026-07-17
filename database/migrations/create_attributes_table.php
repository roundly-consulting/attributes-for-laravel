<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Attributes\Enums\AttributeType;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('owner');
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
