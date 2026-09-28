<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Attributes\Support\TypedValueQuery;

/**
 * The numeric view is the one piece of driver-specific SQL the scopes emit. Building a
 * connection is lazy (no PDO until a query runs), so each grammar can be checked here
 * without the engine being present.
 */
it('casts numbers with a type every engine understands', function (string $driver, string $cast, string $false): void {
    config(["database.connections.numeric_{$driver}" => ['driver' => $driver, 'database' => 'unused', 'prefix' => '']]);

    $query = DB::connection("numeric_{$driver}")->query();

    $sql = TypedValueQuery::numeric($query, 'attributes')->getValue($query->getGrammar());

    expect($sql)->toContain("in ('integer', 'float')")
        ->toContain("= {$false} then cast(")
        ->toContain(" as {$cast}) end");
})->with([
    'sqlite' => ['sqlite', 'real', '0'],
    'pgsql' => ['pgsql', 'double precision', 'false'],
    'mysql' => ['mysql', 'decimal(65, 30)', '0'],
    'mariadb' => ['mariadb', 'decimal(65, 30)', '0'],
    'sqlsrv' => ['sqlsrv', 'float', '0'],
]);
