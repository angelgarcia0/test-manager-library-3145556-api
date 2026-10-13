<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T-02 (second half) · Seed of the five categories.
 *
 * data-model.md section 9: the five categories go in the initial migration with FIXED
 * identifiers. Literal ids, not generated ones, so that a report closed in October can
 * still be compared with the same report after the seed is reloaded.
 *
 * These are the only rows that come from the engine. The initial administrator is NOT
 * seeded here, because its password hash can only be produced by the hasher port, and
 * that belongs to the application, not to the database (D-10, article IX).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement('SET search_path TO library, public');

        foreach ($this->categories() as $category) {
            DB::connection('pgsql')->insert(
                'INSERT INTO library.category (id, name) VALUES (?, ?)',
                [$category['id'], $category['name']]
            );
        }
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DELETE FROM library.category');
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function categories(): array
    {
        return [
            ['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Novela'],
            ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Ciencia'],
            ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'Tecnología'],
            ['id' => '44444444-4444-4444-8444-444444444444', 'name' => 'Historia'],
            ['id' => '55555555-5555-4555-8555-555555555555', 'name' => 'Infantil'],
        ];
    }
};
