<?php

class OrganisationRepository
{
    private PDO $pdo;
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $orgId): ?array
    {
        $statement = $this->pdo->prepare("SELECT org_id, name, username, password_hash FROM organisations WHERE org_id = :org_id LIMIT 1");
        $statement->execute(['org_id' => $orgId]);
        $organisation = $statement->fetch();
        return $organisation ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $statement = $this->pdo->prepare("SELECT org_id, name, username, password_hash FROM organisations WHERE username = :username LIMIT 1");
        $statement->execute(['username' => $username]);
        $organisation = $statement->fetch();
        return $organisation ?: null;
    }
}

class EventRepository
{
    private PDO $pdo;
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findEventForOrganisation(int $orgId, int $eventId): ?array
    {
        $statement = $this->pdo->prepare("\n            SELECT e.event_id AS event_id, e.org_id AS org_id, e.name AS event_name, e.event_date AS event_date,\n                   e.active AS active, e.archive_at AS archive_at, e.purge_at AS purge_at,\n                   o.name AS organisation_name, o.username AS organisation_username\n            FROM events AS e\n            JOIN organisations AS o ON e.org_id = o.org_id\n            WHERE e.event_id = :event_id AND e.org_id = :org_id\n            LIMIT 1\n        ");
        $statement->execute(['event_id' => $eventId, 'org_id' => $orgId]);
        $event = $statement->fetch();
        return $event ?: null;
    }

    public function isEventOpen(array $event): bool
    {
        return isset($event['active']) && (int) $event['active'] === 1;
    }

    public function listForOrganisation(int $orgId, string $search = '', bool $includeCleaned = false, int $limit = 8, int $offset = 0): array
    {
        $where = ['e.org_id = :org_id'];
        $params = ['org_id' => $orgId];
        if ($search !== '') {
            $where[] = 'e.name LIKE :search';
            $params['search'] = '%' . $search . '%';
        }
        if (!$includeCleaned) {
            $where[] = 'e.purge_at > NOW()';
        }
        $whereSql = implode(' AND ', $where);
        $statement = $this->pdo->prepare("\n            SELECT e.event_id, e.org_id, e.name AS event_name, e.event_date, e.active, e.archive_at, e.purge_at,\n                   COUNT(p.photo_id) AS photo_count\n            FROM events AS e\n            LEFT JOIN submissions AS s ON s.event_id = e.event_id\n            LEFT JOIN photos AS p ON p.submission_id = s.submission_id\n            WHERE {$whereSql}\n            GROUP BY e.event_id, e.org_id, e.name, e.event_date, e.active, e.archive_at, e.purge_at\n            ORDER BY e.event_date DESC, e.event_id DESC\n            LIMIT :limit_value OFFSET :offset_value\n        ");
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit_value', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset_value', $offset, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function countForOrganisation(int $orgId, string $search = '', bool $includeCleaned = false): int
    {
        $where = ['org_id = :org_id'];
        $params = ['org_id' => $orgId];
        if ($search !== '') {
            $where[] = 'name LIKE :search';
            $params['search'] = '%' . $search . '%';
        }
        if (!$includeCleaned) {
            $where[] = 'purge_at > NOW()';
        }
        $statement = $this->pdo->prepare('SELECT COUNT(*) AS total FROM events WHERE ' . implode(' AND ', $where));
        $statement->execute($params);
        $row = $statement->fetch();
        return (int) ($row['total'] ?? 0);
    }

    public function archiveExpiredEventsForOrganisation(int $orgId): void
    {
        $statement = $this->pdo->prepare("UPDATE events SET active = 0 WHERE org_id = :org_id AND active = 1 AND archive_at <= NOW()");
        $statement->execute(['org_id' => $orgId]);
    }

    public function listPurgeDueEventsForOrganisation(int $orgId): array
    {
        $statement = $this->pdo->prepare("SELECT event_id, org_id, name AS event_name, purge_at FROM events WHERE org_id = :org_id AND purge_at <= NOW()");
        $statement->execute(['org_id' => $orgId]);
        return $statement->fetchAll();
    }

    public function create(
        int $orgId,
        string $name,
        string $eventDate,
        string $archiveAt,
        string $purgeAt
    ): int {
        $statement = $this->pdo->prepare("
        INSERT INTO events (
            org_id,
            name,
            event_date,
            active,
            archive_at,
            purge_at
        )
        VALUES (
            :org_id,
            :name,
            :event_date,
            1,
            :archive_at,
            :purge_at
        )
    ");

        $statement->execute([
            'org_id' => $orgId,
            'name' => $name,
            'event_date' => $eventDate,
            'archive_at' => $archiveAt,
            'purge_at' => $purgeAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}

class SubmissionRepository
{
    private PDO $pdo;
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $eventId, string $senderName, int $photoCount, int $ownershipConsent, int $postingConsent): int
    {
        $statement = $this->pdo->prepare("\n            INSERT INTO submissions (event_id, sender_name, photo_count, ownership_consent, posting_consent)\n            VALUES (:event_id, :sender_name, :photo_count, :ownership_consent, :posting_consent)\n        ");
        $statement->execute(['event_id' => $eventId, 'sender_name' => $senderName, 'photo_count' => $photoCount, 'ownership_consent' => $ownershipConsent, 'posting_consent' => $postingConsent]);
        return (int) $this->pdo->lastInsertId();
    }
}

class PhotoRepository
{
    private PDO $pdo;
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $submissionId, string $filepath): void
    {
        $statement = $this->pdo->prepare("INSERT INTO photos (submission_id, filepath) VALUES (:submission_id, :filepath)");
        $statement->execute(['submission_id' => $submissionId, 'filepath' => $filepath]);
    }

    public function listForEvent(int $orgId, int $eventId): array
    {
        $statement = $this->pdo->prepare("\n            SELECT p.photo_id, p.submission_id, p.filepath, p.status, p.created_at AS photo_created_at,\n                   s.sender_name, e.event_id, e.name AS event_name, e.org_id\n            FROM photos AS p\n            JOIN submissions AS s ON p.submission_id = s.submission_id\n            JOIN events AS e ON s.event_id = e.event_id\n            WHERE e.org_id = :org_id AND e.event_id = :event_id\n            ORDER BY p.created_at DESC, p.photo_id DESC\n        ");
        $statement->execute(['org_id' => $orgId, 'event_id' => $eventId]);
        return $statement->fetchAll();
    }

    public function listFilepathsForEvent(int $orgId, int $eventId): array
    {
        return array_map(fn(array $photo): array => ['photo_id' => (int) $photo['photo_id'], 'filepath' => $photo['filepath']], $this->listForEvent($orgId, $eventId));
    }

    public function listFilepathsForIds(int $orgId, array $photoIds): array
    {
        $photoIds = array_values(array_filter(array_map('intval', $photoIds), fn($id) => $id > 0));
        if (count($photoIds) === 0)
            return [];
        $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
        $statement = $this->pdo->prepare("\n            SELECT p.photo_id, p.filepath\n            FROM photos AS p\n            JOIN submissions AS s ON p.submission_id = s.submission_id\n            JOIN events AS e ON s.event_id = e.event_id\n            WHERE e.org_id = ? AND p.photo_id IN ({$placeholders})\n        ");
        $statement->execute(array_merge([$orgId], $photoIds));
        return $statement->fetchAll();
    }

    public function deleteByIdsForOrganisation(int $orgId, array $photoIds): array
    {
        $photos = $this->listFilepathsForIds($orgId, $photoIds);
        if (count($photos) === 0)
            return [];
        $ids = array_map(fn(array $photo) => (int) $photo['photo_id'], $photos);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare("\n            DELETE p FROM photos AS p\n            JOIN submissions AS s ON p.submission_id = s.submission_id\n            JOIN events AS e ON s.event_id = e.event_id\n            WHERE e.org_id = ? AND p.photo_id IN ({$placeholders})\n        ");
        $statement->execute(array_merge([$orgId], $ids));
        return $photos;
    }

    public function deleteAllForEvent(int $orgId, int $eventId): array
    {
        $photos = $this->listFilepathsForEvent($orgId, $eventId);
        if (count($photos) === 0)
            return [];
        $statement = $this->pdo->prepare("\n            DELETE p FROM photos AS p\n            JOIN submissions AS s ON p.submission_id = s.submission_id\n            JOIN events AS e ON s.event_id = e.event_id\n            WHERE e.org_id = :org_id AND e.event_id = :event_id\n        ");
        $statement->execute(['org_id' => $orgId, 'event_id' => $eventId]);
        return $photos;
    }
}
