<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('type');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('old_value_type')->nullable();
            $table->string('new_value_type')->nullable();
            $table->json('old_meta')->nullable();
            $table->json('new_meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_type', 'owner_id', 'name']);
        });
    }

    private function table(): string
    {
        $table = config('attributes.history.table', 'attribute_revisions');

        return is_string($table) ? $table : 'attribute_revisions';
    }
};
