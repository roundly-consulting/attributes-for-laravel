<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The host-owned table the suite's attachable entities (Product, DefinedProduct,
 * MethodProduct, MalformedDefProduct, SoftDeletingProduct) live in. It belongs to
 * the fixture, not to the package — attributes attaches through an unconstrained `nullableMorphs('owner')`
 * precisely so a host's entity can live in any table.
 *
 * No `down()`: forward-only is the standard, and the real-engine reset drops every table
 * and re-migrates rather than rolling back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            // Only SoftDeletingProduct uses it: the delete-with-owner hook tells a soft
            // delete from a permanent one.
            $table->softDeletes();
        });
    }
};
