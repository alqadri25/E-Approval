<?php

namespace App\Contracts;

interface SheetRepositoryInterface
{
    /**
     * Get all rows from a table/sheet.
     *
     * @param string $table Tab name (e.g. 'users', 'karyawan', 'dokumen')
     * @param int|null $cacheTtl Cache TTL in seconds (default 60s)
     * @return array
     */
    public function all(string $table, ?int $cacheTtl = 60): array;

    /**
     * Find a single record by a specific column.
     *
     * @param string $table
     * @param string $column
     * @param mixed $value
     * @return array|null
     */
    public function find(string $table, string $column, $value): ?array;

    /**
     * Filter records matching key-value criteria.
     *
     * @param string $table
     * @param array $criteria E.g. ['departemen' => 'PROD 2', 'status' => 'menunggu_foreman']
     * @return array
     */
    public function where(string $table, array $criteria): array;

    /**
     * Insert a single row into table/sheet.
     *
     * @param string $table
     * @param array $data Associative array of column => value
     * @return array The inserted record
     */
    public function insert(string $table, array $data): array;

    /**
     * Batch insert multiple rows.
     *
     * @param string $table
     * @param array $rows Array of associative arrays
     * @return int Count of rows inserted
     */
    public function batchInsert(string $table, array $rows): int;

    /**
     * Update record matching key column.
     *
     * @param string $table
     * @param string $keyColumn
     * @param mixed $keyValue
     * @param array $data Updated values
     * @return bool
     */
    public function update(string $table, string $keyColumn, $keyValue, array $data): bool;

    /**
     * Delete record matching key column.
     *
     * @param string $table
     * @param string $keyColumn
     * @param mixed $keyValue
     * @return bool
     */
    public function delete(string $table, string $keyColumn, $keyValue): bool;

    /**
     * Setup sheets tabs and headers.
     *
     * @return array Setup status report per sheet
     */
    public function setupSchema(): array;

    /**
     * Invalidate cache for a specific table or all tables.
     *
     * @param string|null $table
     */
    public function clearCache(?string $table = null): void;

    /**
     * Check if currently connected to Google Sheets
     *
     * @return bool
     */
    public function isOnline(): bool;
}

