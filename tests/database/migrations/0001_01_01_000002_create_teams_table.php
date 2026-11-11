<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The host-owned scope model of the `unique => scope` tests: a team (a tenant) the
 * products belong to through the nullable `products.team_id` this migration adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('team_id')->nullable();
        });
    }
};
