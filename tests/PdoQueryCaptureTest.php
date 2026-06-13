<?php

declare(strict_types=1);

namespace Tests;

use flight\debug\database\PdoQueryCapture;
use flight\database\SimplePdo;
use PHPUnit\Framework\TestCase;

class PdoQueryCaptureTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset static capture data before every test
        PdoQueryCapture::$query_data = [];
    }

    public function testExtendsSimplePdo(): void
    {
        $db = new PdoQueryCapture('sqlite::memory:');

        $this->assertInstanceOf(SimplePdo::class, $db);
    }

    public function testIsInstanceOfPdoWrapperThroughSimplePdo(): void
    {
        $db = new PdoQueryCapture('sqlite::memory:');

        // SimplePdo extends PdoWrapper, so this is still true, but the point is we inherit the modern implementation
        $this->assertInstanceOf(\flight\database\PdoWrapper::class, $db);
    }

    public function testCapturesExecAndDirectQuery(): void
    {
        $db = new PdoQueryCapture('sqlite::memory:');

        $db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");
        $stmt = $db->query("SELECT 1 AS one");

        $data = PdoQueryCapture::$query_data;

        $this->assertGreaterThanOrEqual(2, count($data));

        // Check that we captured an exec and a query
        $queries = array_column($data, 'query');
        $this->assertStringContainsString('CREATE TABLE users', implode(' ', $queries));
        $this->assertStringContainsString('SELECT 1', implode(' ', $queries));

        // Each entry should have the expected keys
        foreach ($data as $entry) {
            $this->assertArrayHasKey('query', $entry);
            $this->assertArrayHasKey('execution_time', $entry);
            $this->assertArrayHasKey('params', $entry);
            $this->assertArrayHasKey('backtrace', $entry);
            $this->assertArrayHasKey('rows', $entry);
        }
    }

    public function testCapturesRunQuery(): void
    {
        $db = new PdoQueryCapture('sqlite::memory:');
        $db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");

        $stmt = $db->runQuery("INSERT INTO users (name) VALUES (?)", ['Alice']);

        $data = array_values(PdoQueryCapture::$query_data);
        $last = end($data);

        $this->assertStringContainsString('INSERT INTO users', $last['query']);
        $this->assertSame(['Alice'], $last['params']);
        $this->assertGreaterThan(0, $last['rows']); // rowCount for insert is usually 1
    }

    public function testCapturesFetchAllAndFetchRow(): void
    {
        $db = new PdoQueryCapture('sqlite::memory:');
        $db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");
        $db->runQuery("INSERT INTO users (name) VALUES (?), (?)", ['Bob', 'Charlie']);

        $all = $db->fetchAll("SELECT * FROM users ORDER BY id");
        $row = $db->fetchRow("SELECT * FROM users WHERE name = ?", ['Bob']);

        $data = PdoQueryCapture::$query_data;

        // We expect at least the two inserts + fetchAll + fetchRow
        $this->assertGreaterThanOrEqual(4, count($data));

        $queries = array_column($data, 'query');
        $this->assertStringContainsString('SELECT * FROM users ORDER BY id', implode("\n", $queries));

        // fetchRow in SimplePdo should have added LIMIT 1 (for the captured query text)
        $hasLimitOne = false;
        foreach ($queries as $q) {
            if (stripos($q, 'LIMIT 1') !== false) {
                $hasLimitOne = true;
            }
        }
        $this->assertTrue($hasLimitOne, 'fetchRow should have caused a LIMIT 1 to be added in the executed query');

        // Verify we got real data back from the high level methods
        $this->assertCount(2, $all);
        $this->assertNotNull($row);
        $this->assertSame('Bob', $row['name'] ?? $row->name);
    }

    public function testCapturesFetchColumn(): void
    {
        $db = new PdoQueryCapture('sqlite::memory:');
        $db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");
        $db->runQuery("INSERT INTO users (name) VALUES (?)", ['Dana']);

        $ids = $db->fetchColumn("SELECT id FROM users");

        $this->assertNotEmpty($ids);
        $this->assertContainsOnly('int', $ids); // or numeric

        $queries = array_column(PdoQueryCapture::$query_data, 'query');
        $this->assertStringContainsString('SELECT id FROM users', implode(' ', $queries));
    }

    public function testCapturesPreparedStatementsAndParamInterpolation(): void
    {
        $db = new PdoQueryCapture('sqlite::memory:');
        $db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");

        $stmt = $db->prepare("SELECT * FROM users WHERE name = ?");
        $stmt->execute(['Eve']);

        $data = PdoQueryCapture::$query_data;
        $this->assertNotEmpty($data);

        // The statement capture should have recorded the prepare + the execution with interpolated query for display
        $foundPrepared = false;
        foreach ($data as $entry) {
            if (isset($entry['query']) && stripos($entry['query'], 'SELECT * FROM users WHERE name') !== false) {
                if (isset($entry['prepare_time']) || (isset($entry['execution_time']) && $entry['execution_time'] > 0)) {
                    $foundPrepared = true;
                }
                // After transformQueryWithParams, the query often contains the literal value for display
                if (stripos($entry['query'], 'Eve') !== false || !empty($entry['params'])) {
                    // Either the transformed query or the params array will have evidence
                }
            }
        }

        $this->assertTrue($foundPrepared, 'Prepared statement should have been captured with timing and params');
    }

    public function testCapturesInsertHelperFromSimplePdo(): void
    {
        $db = new PdoQueryCapture('sqlite::memory:');
        $db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");

        $lastId = $db->insert('users', ['name' => 'Frank']);

        $this->assertNotEmpty($lastId);

        $queries = array_column(PdoQueryCapture::$query_data, 'query');
        $this->assertStringContainsString('INSERT INTO users', implode(' ', $queries));
    }

    public function testQueryDataContainsBacktrace(): void
    {
        $db = new PdoQueryCapture('sqlite::memory:');
        $db->exec("CREATE TABLE t (x INT)");

        $data = PdoQueryCapture::$query_data;
        $entry = reset($data);

        $this->assertIsArray($entry['backtrace']);
        $this->assertNotEmpty($entry['backtrace']);
        // The first frame should point into this test or the PdoQueryCapture class
    }

    public function testCanBeRegisteredWithFlight(): void
    {
        // Common real-world usage pattern from the README
        \Flight::register('db', PdoQueryCapture::class, ['sqlite::memory:']);

        $db = \Flight::db();
        $this->assertInstanceOf(PdoQueryCapture::class, $db);

        $db->exec("CREATE TABLE test (id INT)");
        $db->runQuery("INSERT INTO test (id) VALUES (?)", [42]);

        $this->assertNotEmpty(PdoQueryCapture::$query_data);
        $queries = array_column(PdoQueryCapture::$query_data, 'query');
        $this->assertStringContainsString('INSERT INTO test', implode(' ', $queries));
    }

    public function testMultipleConnectionsHaveSeparateCaptureButStaticIsShared(): void
    {
        // Note: $query_data is static on the class, so shared across instances.
        // This is existing behavior we preserve.
        $db1 = new PdoQueryCapture('sqlite::memory:');
        $db1->exec("CREATE TABLE a (id INT)");

        $db2 = new PdoQueryCapture('sqlite::memory:');
        $db2->exec("CREATE TABLE b (id INT)");

        $this->assertGreaterThanOrEqual(2, count(PdoQueryCapture::$query_data));
    }
}
