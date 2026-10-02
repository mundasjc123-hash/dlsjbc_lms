<?php
/**
 * Catalog domain logic: "what is this, and what do we call it?"
 *
 * A Title here is really a bib_record + its one edition (this project
 * keeps one edition per title for now - the schema allows more later,
 * nothing here prevents extending to that). A Title can have zero or
 * more physical copies (items).
 *
 * Other features must not query bib_records/editions/items/authors
 * directly - call these static methods instead (see AI_CONTEXT.md).
 */
class Title
{
    /** Search titles by title/author/ISBN. Empty $q returns everything. */
    public static function search(string $q = ''): array
    {
        $pdo = Database::connection();
        $like = '%' . $q . '%';

        $stmt = $pdo->prepare(
            "SELECT br.id, br.title,
                    e.id AS edition_id, e.isbn, e.format,
                    (SELECT GROUP_CONCAT(a.name SEPARATOR ', ')
                       FROM bib_authors ba JOIN authors a ON a.id = ba.author_id
                      WHERE ba.bib_record_id = br.id) AS authors,
                    COUNT(i.id) AS total_copies,
                    SUM(CASE WHEN i.status = 'available' THEN 1 ELSE 0 END) AS available_copies
             FROM bib_records br
             LEFT JOIN editions e ON e.bib_record_id = br.id
             LEFT JOIN items i ON i.edition_id = e.id
             WHERE br.title LIKE ? OR e.isbn LIKE ?
                OR EXISTS (SELECT 1 FROM bib_authors ba2 JOIN authors a2 ON a2.id = ba2.author_id
                            WHERE ba2.bib_record_id = br.id AND a2.name LIKE ?)
             GROUP BY br.id, e.id
             ORDER BY br.title
             LIMIT 200"
        );
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll();
    }

    /** One title (bib_record + its edition) plus its authors and copies. Null if not found. */
    public static function find(int $bibRecordId): ?array
    {
        self::ensureCallNoColumn();
        $pdo = Database::connection(); 

        $stmt = $pdo->prepare(
            "SELECT br.id, br.title, br.summary, br.issn, br.frequency,
                    e.id AS edition_id, e.isbn, e.publisher, e.publication_year, e.format
             FROM bib_records br
             JOIN editions e ON e.bib_record_id = br.id
             WHERE br.id = ?
             LIMIT 1"
        );
        $stmt->execute([$bibRecordId]);
        $title = $stmt->fetch();

        if (!$title) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT a.name FROM bib_authors ba JOIN authors a ON a.id = ba.author_id
             WHERE ba.bib_record_id = ? ORDER BY a.name"
        );
        $stmt->execute([$bibRecordId]);
        $title['authors'] = array_column($stmt->fetchAll(), 'name');

       $stmt = $pdo->prepare(
            "SELECT i.id, i.barcode, i.status, i.item_condition, i.price,
                    i.volume_no, i.issue_no, i.date_published,
                    mt.name AS material_type_name, c.name AS collection_name, l.name AS location_name
             FROM items i
             JOIN material_types mt ON mt.id = i.material_type_id
             JOIN collections c ON c.id = i.collection_id
             LEFT JOIN locations l ON l.id = i.location_id
             WHERE i.edition_id = ?
             ORDER BY i.barcode"
        );
        $stmt->execute([$title['edition_id']]);
        $title['copies'] = $stmt->fetchAll();

        return $title;
    }

    /** Create a new title. $data keys: title, authors (comma-separated string), isbn, publisher, publication_year, format, summary. Returns the new bib_record id. */
    public static function create(array $data): int
    {
        self::ensureCallNoColumn();
        $pdo = Database::connection();

        $stmt = $pdo->prepare('INSERT INTO bib_records (title, summary) VALUES (?, ?)');
        $stmt->execute([$data['title'], $data['summary'] ?: null]);
        $bibId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO editions (bib_record_id, isbn, publisher, publication_year, format, call_no)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $bibId,
            $data['isbn'] ?: null,
            $data['publisher'] ?: null,
            $data['publication_year'] ?: null,
            $data['format'] ?: null,
            ($data['call_no'] ?? '') ?: null,
        ]);

        self::saveAuthors($bibId, $data['authors']);

        return $bibId;
    }

    /** Update an existing title and its edition. */
    public static function update(int $bibId, int $editionId, array $data): void
    {
        self::ensureCallNoColumn();
        $pdo = Database::connection();

        $stmt = $pdo->prepare('UPDATE bib_records SET title = ?, summary = ? WHERE id = ?');
        $stmt->execute([$data['title'], $data['summary'] ?: null, $bibId]);

        $stmt = $pdo->prepare(
            'UPDATE editions SET isbn = ?, publisher = ?, publication_year = ?, format = ?, call_no = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['isbn'] ?: null,
            $data['publisher'] ?: null,
            $data['publication_year'] ?: null,
            $data['format'] ?: null,
            ($data['call_no'] ?? '') ?: null,
            $editionId,
        ]);

        self::saveAuthors($bibId, $data['authors']);
    }

    /** Add a physical copy to an edition. $data keys: barcode, material_type_id, collection_id, location_id, price. Returns the new item id. */
    public static function addCopy(int $editionId, array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "INSERT INTO items (edition_id, material_type_id, collection_id, location_id, barcode, volume_no, issue_no, date_published, status, item_condition, date_acquired, price)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', 'good', CURDATE(), ?)"
        );
        $stmt->execute([
            $editionId,
            $data['material_type_id'],
            $data['collection_id'],
            $data['location_id'] ?: null,
            $data['barcode'],
            $data['volume_no'] ?: null,
            $data['issue_no'] ?: null,
            $data['date_published'] ?: null,
            $data['price'] ?: null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Periodicals for the Serials list: ISSN, frequency, latest issue label, and total issue count. */
    public static function periodicals(string $q = ''): array
    {
        $pdo = Database::connection();
        $like = '%' . $q . '%';

        $stmt = $pdo->prepare(
            "SELECT br.id, br.title, br.issn, br.frequency,
                    (SELECT GROUP_CONCAT(a.name SEPARATOR ', ')
                       FROM bib_authors ba JOIN authors a ON a.id = ba.author_id
                      WHERE ba.bib_record_id = br.id) AS authors,
                    COUNT(i.id) AS total_issues,
                    (SELECT CONCAT_WS(' ',
                                CASE WHEN i2.volume_no IS NOT NULL THEN CONCAT('Vol. ', i2.volume_no) END,
                                CASE WHEN i2.issue_no IS NOT NULL THEN CONCAT('No. ', i2.issue_no) END,
                                CASE WHEN i2.date_published IS NOT NULL THEN CONCAT('(', DATE_FORMAT(i2.date_published, '%b %Y'), ')') END
                            )
                       FROM items i2
                       JOIN material_types mt2 ON mt2.id = i2.material_type_id
                      WHERE i2.edition_id = e.id AND mt2.name = 'Periodical'
                      ORDER BY COALESCE(i2.date_published, i2.date_acquired) DESC, i2.id DESC
                      LIMIT 1) AS latest_issue
             FROM bib_records br
             JOIN editions e ON e.bib_record_id = br.id
             JOIN items i ON i.edition_id = e.id
             JOIN material_types mt ON mt.id = i.material_type_id
             WHERE mt.name = 'Periodical' AND br.title LIKE ?
             GROUP BY br.id, e.id
             ORDER BY br.title"
        );
        $stmt->execute([$like]);
        return $stmt->fetchAll();
    }

    /** Most recently added titles, for the OPAC homepage. */
    public static function recentlyAdded(int $limit = 6): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT br.id,
                    br.title,
                    (SELECT GROUP_CONCAT(a.name SEPARATOR ', ')
                       FROM bib_authors ba JOIN authors a ON a.id = ba.author_id
                      WHERE ba.bib_record_id = br.id) AS authors
             FROM bib_records br
             JOIN editions e ON e.bib_record_id = br.id
             ORDER BY br.created_at DESC, br.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function materialTypes(): array
    {
        return Database::connection()->query('SELECT id, name FROM material_types ORDER BY name')->fetchAll();
    }

    public static function collections(): array
    {
        return Database::connection()->query('SELECT id, name FROM collections ORDER BY name')->fetchAll();
    }

    public static function locations(): array
    {
        return Database::connection()->query('SELECT id, name FROM locations ORDER BY name')->fetchAll();
    }

    /** Replaces this title's author links from a comma-separated name string, creating authors that don't exist yet. */
    private static function saveAuthors(int $bibId, string $namesCsv): void
    {
        $pdo = Database::connection();

        $pdo->prepare('DELETE FROM bib_authors WHERE bib_record_id = ?')->execute([$bibId]);

        $names = array_filter(array_map('trim', explode(',', $namesCsv)));
        foreach ($names as $name) {
            $stmt = $pdo->prepare('SELECT id FROM authors WHERE name = ?');
            $stmt->execute([$name]);
            $authorId = $stmt->fetchColumn();

            if ($authorId === false) {
                $stmt = $pdo->prepare('INSERT INTO authors (name) VALUES (?)');
                $stmt->execute([$name]);
                $authorId = $pdo->lastInsertId();
            }

            $pdo->prepare('INSERT IGNORE INTO bib_authors (bib_record_id, author_id) VALUES (?, ?)')
                ->execute([$bibId, $authorId]);
        }
    }

    /** Older databases have no editions.call_no yet - add it once, automatically. */
    private static function ensureCallNoColumn(): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'editions' AND COLUMN_NAME = 'call_no'"
        );
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE editions ADD COLUMN call_no VARCHAR(50) NULL AFTER isbn");
        }
    }

    /**
     * Rows for the staff Inventory > Physical Books table: one row per title
     * (including titles that have no copies yet), with copy counts and the
     * accession numbers of its copies.
     *
     * $filters keys (all optional): collection_id, status (available|borrowed|none),
     * publisher, year_from, year_to.
     */
    public static function inventoryList(string $q = '', array $filters = []): array
    {
        self::ensureCallNoColumn();
        $like = '%' . $q . '%';

        $where = [
            "(br.title LIKE ? OR e.call_no LIKE ? OR i.accession_number LIKE ? OR i.barcode LIKE ?
              OR EXISTS (SELECT 1 FROM bib_authors ba2 JOIN authors a2 ON a2.id = ba2.author_id
                          WHERE ba2.bib_record_id = br.id AND a2.name LIKE ?))"
        ];
        $params = [$like, $like, $like, $like, $like];

        if (!empty($filters['collection_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM items i2 WHERE i2.edition_id = e.id AND i2.collection_id = ?)';
            $params[] = (int) $filters['collection_id'];
        }
        if (($filters['publisher'] ?? '') !== '') {
            $where[] = 'e.publisher LIKE ?';
            $params[] = '%' . $filters['publisher'] . '%';
        }
        if (!empty($filters['year_from'])) {
            $where[] = 'e.publication_year >= ?';
            $params[] = (int) $filters['year_from'];
        }
        if (!empty($filters['year_to'])) {
            $where[] = 'e.publication_year <= ?';
            $params[] = (int) $filters['year_to'];
        }

        $havingByStatus = [
            'available' => 'available_copies > 0',
            'borrowed'  => 'total_copies > 0 AND available_copies = 0',
            'none'      => 'total_copies = 0',
        ];
        $having = isset($havingByStatus[$filters['status'] ?? ''])
            ? 'HAVING ' . $havingByStatus[$filters['status']]
            : '';

        $stmt = Database::connection()->prepare(
            "SELECT br.id, br.title,
                    e.call_no, e.edition_statement, e.publisher, e.publication_year,
                    (SELECT GROUP_CONCAT(a.name SEPARATOR ', ')
                       FROM bib_authors ba JOIN authors a ON a.id = ba.author_id
                      WHERE ba.bib_record_id = br.id) AS authors,
                    GROUP_CONCAT(COALESCE(i.accession_number, i.barcode) ORDER BY i.id SEPARATOR '; ') AS accessions,
                    GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') AS book_location,
                    COUNT(i.id) AS total_copies,
                    COALESCE(SUM(CASE WHEN i.status = 'available' THEN 1 ELSE 0 END), 0) AS available_copies
             FROM bib_records br
             LEFT JOIN editions e ON e.bib_record_id = br.id
             LEFT JOIN items i ON i.edition_id = e.id
             LEFT JOIN collections c ON c.id = i.collection_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY br.id, e.id
             $having
             ORDER BY br.id DESC
             LIMIT 500"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
