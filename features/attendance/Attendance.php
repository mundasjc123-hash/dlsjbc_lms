<?php

class Attendance
{
    public const PURPOSES = [
        'Study/Reading',
        'Borrow/Return book',
        'Research/Reference',
        'Computer/Internet use',
        'Other',
    ];

    public static function logPatronEntry(int $userId, string $purpose, int $loggedBy): array
    {
        if (self::hasOpenLogForUser($userId)) {
            return ['ok' => false, 'error' => 'This person is already checked in (no time-out recorded yet).'];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO attendance_logs (visitor_type, user_id, purpose, logged_by)
             VALUES ('patron', ?, ?, ?)"
        );
        $stmt->execute([$userId, $purpose, $loggedBy]);

        return ['ok' => true, 'log_id' => (int) $pdo->lastInsertId()];
    }

    public static function logUnregisteredPatronEntry(string $patronType, string $idNumber, string $fullName, string $purpose, int $loggedBy): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO attendance_logs (visitor_type, guest_name, patron_type_hint, manual_id_number, purpose, logged_by)
             VALUES ('patron', ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$fullName, $patronType, $idNumber !== '' ? $idNumber : null, $purpose, $loggedBy]);

        return ['ok' => true, 'log_id' => (int) $pdo->lastInsertId()];
    }

    public static function logGuestEntry(string $guestName, string $purpose, int $loggedBy): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO attendance_logs (visitor_type, guest_name, purpose, logged_by)
             VALUES ('guest', ?, ?, ?)"
        );
        $stmt->execute([$guestName, $purpose, $loggedBy]);

        return ['ok' => true, 'log_id' => (int) $pdo->lastInsertId()];
    }

    public static function logExit(int $logId, int $staffUserId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "SELECT al.id, al.visitor_type, al.guest_name, al.user_id, u.full_name
             FROM attendance_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.id = ? AND al.time_out IS NULL
             LIMIT 1"
        );
        $stmt->execute([$logId]);
        $log = $stmt->fetch();

        if (!$log) {
            return ['ok' => false, 'error' => 'That entry is not open (already checked out?).'];
        }

        $pdo->prepare('UPDATE attendance_logs SET time_out = NOW(), checked_out_by = ? WHERE id = ?')
            ->execute([$staffUserId, $logId]);

        return ['ok' => true, 'name' => $log['user_id'] === null ? $log['guest_name'] : $log['full_name']];
    }

    
    public static function currentlyInside(): array
    {
        $stmt = Database::connection()->query(
            "SELECT al.id, al.visitor_type, al.guest_name, al.patron_type_hint, al.manual_id_number,
                    al.purpose, al.time_in,
                    u.full_name, u.id_number, pc.name AS category_name
             FROM attendance_logs al
             LEFT JOIN users u ON u.id = al.user_id
             LEFT JOIN patron_profiles pp ON pp.user_id = u.id
             LEFT JOIN patron_categories pc ON pc.id = pp.patron_category_id
             WHERE al.time_out IS NULL
             ORDER BY al.time_in DESC"
        );
        return $stmt->fetchAll();
    }

   
    public static function logForRange(string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT al.id, al.visitor_type, al.guest_name, al.patron_type_hint, al.manual_id_number,
                    al.purpose, al.time_in, al.time_out,
                    u.full_name, u.id_number, pc.name AS category_name
             FROM attendance_logs al
             LEFT JOIN users u ON u.id = al.user_id
             LEFT JOIN patron_profiles pp ON pp.user_id = u.id
             LEFT JOIN patron_categories pc ON pc.id = pp.patron_category_id
             WHERE DATE(al.time_in) BETWEEN ? AND ?
             ORDER BY al.time_in DESC"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }

    
    public static function nameCell(array $v): string
    {
        if ($v['visitor_type'] === 'guest') {
            return htmlspecialchars($v['guest_name']);
        }
        if ($v['id_number']) {
            return htmlspecialchars($v['full_name']) . ' (' . htmlspecialchars($v['id_number']) . ')';
        }
        $label = htmlspecialchars($v['guest_name']);
        if ($v['manual_id_number']) {
            $label .= ' (typed ID: ' . htmlspecialchars($v['manual_id_number']) . ')';
        }
        return $label;
    }

    
    public static function typeBadge(array $v): string
    {
        if ($v['visitor_type'] === 'guest') {
            return '<span class="badge">Guest</span>';
        }
        if ($v['id_number']) {
            return '<span class="badge">' . htmlspecialchars($v['category_name'] ?? 'Patron') . '</span>';
        }
        $type = $v['patron_type_hint'] === 'student' ? 'Student' : 'Staff/Faculty';
        return '<span class="badge">' . $type . ' (not in system)</span>';
    }

    private static function hasOpenLogForUser(int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM attendance_logs WHERE user_id = ? AND time_out IS NULL"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}