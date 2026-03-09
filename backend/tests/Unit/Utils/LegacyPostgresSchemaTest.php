<?php

namespace Tests\Unit\Utils;

use App\Utils\LegacyPostgresSchema;
use Mockery;
use PHPUnit\Framework\TestCase;

class LegacyPostgresSchemaTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_has_column_uses_explicit_schema_when_qualified(): void
    {
        $connection = Mockery::mock();

        $dbMock = Mockery::mock('alias:Illuminate\\Support\\Facades\\DB');
        $dbMock->shouldReceive('connection')->once()->with('db90')->andReturn($connection);

        $connection->shouldReceive('selectOne')
            ->once()
            ->with(
                Mockery::on(fn (string $sql) => str_contains($sql, 'n.nspname = ?') && !str_contains($sql, 'pg_table_is_visible')),
                ['rs_ansdata_1_1_1', 'q1', 'public']
            )
            ->andReturn((object) ['found' => 1]);

        $schema = new LegacyPostgresSchema('db90');

        $this->assertTrue($schema->hasColumn('public.rs_ansdata_1_1_1', 'q1'));
    }

    public function test_has_column_returns_false_for_invalid_identifier(): void
    {
        $schema = new LegacyPostgresSchema('db90');

        $this->assertFalse($schema->hasColumn('rs_ansdata_1_1_1', 'q-1'));
    }
}
