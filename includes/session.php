<?php
/**
 * Stores PHP sessions in the database instead of files on the server's disk, so logins survive
 * restarts, redeploys and hosts that wipe the filesystem (e.g. free container tiers).
 */
final class DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private int $lifetime;

    public function __construct(int $lifetime)
    {
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = db()->prepare('SELECT data FROM sessions WHERE id = ? AND last_activity > ?');
        $stmt->execute([$id, time() - $this->lifetime]);
        $data = $stmt->fetchColumn();
        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        db()->prepare(
            'INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)'
        )->execute([$id, $data, time()]);
        return true;
    }

    public function destroy(string $id): bool
    {
        db()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = db()->prepare('DELETE FROM sessions WHERE last_activity < ?');
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }

    /** With session.use_strict_mode, only ids that already exist are accepted from the browser. */
    public function validateId(string $id): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM sessions WHERE id = ? AND last_activity > ?');
        $stmt->execute([$id, time() - $this->lifetime]);
        return (bool) $stmt->fetchColumn();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        db()->prepare('UPDATE sessions SET last_activity = ? WHERE id = ?')->execute([time(), $id]);
        return true;
    }
}
