<?php
/**
 * Database Connection (PDO)
 * Dipanggil di semua file backend
 */

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}

class DB
{
    private static ?PDO $instance = null;

    /** ===============================
     *  CONNECT PDO SINGLETON
     *  ================================
     */
    public static function connect(): PDO
    {
        if (self::$instance === null) {
            try {
                self::$instance = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                http_response_code(500);
                die("Database error: " . $e->getMessage());
            }
        }
        return self::$instance;
    }

    /** ===============================
     *  QUERY (prepare + execute)
     *  ================================
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** ===============================
     *  EXECUTE (insert/update/delete)
     *  ================================
     *  Mengembalikan boolean
     */
    public static function exec(string $sql, array $params = []): bool
    {
        return self::query($sql, $params)->rowCount() > 0;
    }

    /** ===============================
     *  EXECUTE CUSTOM (mirip execute())
     *  ================================
     *  Mengembalikan PDOStatement
     */
    public static function execute(string $sql, array $params = []): PDOStatement
    {
        return self::query($sql, $params);
    }

    /** ===============================
     *  FETCH SINGLE ROW
     *  ================================
     */
    public static function fetch(string $sql, array $params = [])
    {
        return self::query($sql, $params)->fetch();
    }

    /** ===============================
     *  FETCH ALL ROWS
     *  ================================
     */
    public static function fetchAll(string $sql, array $params = [])
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** ===============================
     *  LAST INSERT ID
     *  ================================
     */
    public static function lastInsertId(): string
    {
        return self::connect()->lastInsertId();
    }

    public static function lastId(): string
    {
        return self::connect()->lastInsertId();
    }

    /** ===============================
     *  COUNT ROWS
     *  ================================
     */
    public static function count(string $table, string $where = '1=1', array $params = []): int
    {
        $sql = "SELECT COUNT(*) AS total FROM {$table} WHERE {$where}";
        $row = self::fetch($sql, $params);
        return (int) ($row['total'] ?? 0);
    }

/** ===============================
 *  FETCH SINGLE COLUMN
 *  ================================
 */
public static function fetchColumn(string $sql, array $params = [])
{
    return self::query($sql, $params)->fetchColumn();
}

/** ===============================
 *  TRANSACTION HANDLER
 *  ================================
 */
public static function begin(): void
{
    self::connect()->beginTransaction();
}

public static function commit(): void
{
    if (self::connect()->inTransaction()) {
        self::connect()->commit();
    }
}

public static function rollback(): void
{
    if (self::connect()->inTransaction()) {
        self::connect()->rollBack();
    }
}

}