<?php
declare(strict_types=1);

final class DeathRecordModel extends BaseModel implements Crudable
{
    protected string $table = 'death_records';

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, ru.full_name AS registered_by_name, au.full_name AS approved_by_name
            FROM death_records d
            LEFT JOIN users ru ON ru.id = d.registered_by
            LEFT JOIN users au ON au.id = d.approved_by
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = "(deceased_name LIKE ? OR certificate_no LIKE ? OR applicant_name LIKE ?)";
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['region'])) {
            $where[] = "region = ?";
            $params[] = $filters['region'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "date_of_death >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "date_of_death <= ?";
            $params[] = $filters['date_to'];
        }

        $sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        return [$sql, $params];
    }

    public function all(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT d.*, ru.full_name AS registered_by_name
                FROM death_records d
                LEFT JOIN users ru ON ru.id = d.registered_by
                $whereSql
                ORDER BY d.id DESC LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($i++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function count(array $filters = []): int
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) c FROM death_records $whereSql");
        $stmt->execute($params);
        return (int)$stmt->fetch()['c'];
    }

    public function create(array $data): int
    {
        $certNo = $this->generateCertificateNumber();
        $stmt = $this->pdo->prepare("
            INSERT INTO death_records
            (certificate_no, deceased_name, passport_number, gender, date_of_birth, date_of_death, place_of_death,
             cause_of_death, hospital_name, district, region, applicant_name, applicant_relationship,
             applicant_contact, status, registered_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pending', ?)
        ");
        $stmt->execute([
            $certNo, $data['deceased_name'], $data['passport_number'] ?? null, $data['gender'], $data['date_of_birth'] ?: null,
            $data['date_of_death'], $data['place_of_death'], $data['cause_of_death'],
            $data['hospital_name'] ?: null, $data['district'], $data['region'],
            $data['applicant_name'], $data['applicant_relationship'], $data['applicant_contact'],
            $data['registered_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE death_records SET
                deceased_name = ?, passport_number = ?, gender = ?, date_of_birth = ?, date_of_death = ?,
                place_of_death = ?, cause_of_death = ?, hospital_name = ?, district = ?,
                region = ?, applicant_name = ?, applicant_relationship = ?, applicant_contact = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['deceased_name'], $data['passport_number'] ?? null, $data['gender'], $data['date_of_birth'] ?: null,
            $data['date_of_death'], $data['place_of_death'], $data['cause_of_death'],
            $data['hospital_name'] ?: null, $data['district'], $data['region'],
            $data['applicant_name'], $data['applicant_relationship'], $data['applicant_contact'],
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM death_records WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function setStatus(int $id, string $status, int $approverId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE death_records SET status = ?, approved_by = ?, updated_at = NOW() WHERE id = ?
        ");
        return $stmt->execute([$status, $approverId, $id]);
    }

    private function generateCertificateNumber(): string
    {
        $year = date('Y');
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) c FROM death_records WHERE certificate_no LIKE ?
        ");
        $stmt->execute(["DRS-$year-%"]);
        $seq = (int)$stmt->fetch()['c'] + 1;
        return sprintf('DRS-%s-%06d', $year, $seq);
    }

    // ---- statistics for dashboard/reports ----
    public function statusCounts(): array
    {
        $rows = $this->pdo->query("SELECT status, COUNT(*) c FROM death_records GROUP BY status")->fetchAll();
        $out = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int)$r['c'];
        }
        return $out;
    }

    public function genderCounts(): array
    {
        $rows = $this->pdo->query("SELECT gender, COUNT(*) c FROM death_records GROUP BY gender")->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['gender']] = (int)$r['c'];
        }
        return $out;
    }

    public function monthlyTrend(): array
    {
        $rows = $this->pdo->query("
            SELECT DATE_FORMAT(date_of_death, '%Y-%m') ym, COUNT(*) c
            FROM death_records GROUP BY ym ORDER BY ym DESC LIMIT 12
        ")->fetchAll();
        return array_reverse($rows);
    }

    public function totalCount(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) c FROM death_records")->fetch()['c'];
    }
}
