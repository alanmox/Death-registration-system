<?php
declare(strict_types=1);

interface Crudable
{
    public function find(int $id): ?array;
    public function all(array $filters, int $page, int $perPage): array;
    public function count(array $filters): int;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
