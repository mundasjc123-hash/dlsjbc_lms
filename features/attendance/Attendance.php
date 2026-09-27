<?php
/**
 * Attendance domain logic: "who's in the library right now, and who was
 * in today?" - separate from Circulation, which answers "who has an item
 * checked out." A visit here does not require borrowing anything.
 *
 * Patron lookup reuses Loan::findPatronByIdNumber() instead of writing a
 * second query against users/patron_profiles (see AI_CONTEXT.md,
 * convention #3 - call another feature's class, don't re-query its
 * tables).
 */
class Attendance
{
    /** Purposes shown on the check-in form. Stored as plain text, not an ENUM, so this list can change without a schema edit. */
    public const PURPOSES = [
        'Study/Reading',
        'Borrow/Return book',
        'Research/Reference',
        'Computer/Internet use',
        'Other',
    ];

    /** Logs a patron (student/staff/faculty account) entering. Returns ['ok'=>bool, 'error'?, 'log_id'?]. */
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

    /** Logs a guest (no account) entering. Returns ['ok'=>bool, 'error'?, 'log_id'?]. */
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

    /** Records someone leaving. Returns ['ok'=>bool, 'error'?, 'name'?]. */
    public static function logExit(int $logId, int $staffUserId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "SELECT al.id, al.visitor_type, al.guest_name, u.full_name
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

        return ['ok' => true, 'name' => $log['visitor_type'] === 'guest' ? $log['guest_name'] : $log['full_name']];
    }

    /** Everyone currently inside (no time_out yet), most recent first. */
    public static function currentlyInside(): array
    {
        $stmt = Database::connection()->query(
            "SELECT al.id, al.visitor_type, al.guest_name, al.purpose, al.time_in,
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

    /** Every log entry from today (in and out), most recent first. */
    public static function today(): array
    {
        $stmt = Database::connection()->query(
            "SELECT al.id, al.visitor_type, al.guest_name, al.purpose, al.time_in, al.time_out,
                    u.full_name, u.id_number, pc.name AS category_name
             FROM attendance_logs al
             LEFT JOIN users u ON u.id = al.user_id
             LEFT JOIN patron_profiles pp ON pp.user_id = u.id
             LEFT JOIN patron_categories pc ON pc.id = pp.patron_category_id
             WHERE DATE(al.time_in) = CURDATE()
             ORDER BY al.time_in DESC"
        );
        return $stmt->fetchAll();
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