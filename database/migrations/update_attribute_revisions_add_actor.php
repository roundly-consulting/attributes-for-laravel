<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Attributes\Support\AttributesConfig;

/**
 * Who made a revision: a nullable `actor` morph, additive to the shipped revisions table.
 * Named to sort after the `create_*` migrations, so it is published (and run) after them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Throws for an unrecognized value, so a typo fails the migration loudly.
        $keyType = AttributesConfig::actorKeyType();

        Schema::table(AttributesConfig::historyTable(), function (Blueprint $table) use ($keyType): void {
            $table->morphKey('actor', $keyType, nullable: true);
        });
    }
};
