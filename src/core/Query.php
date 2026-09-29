<?php

namespace Core;

use PDO;
use App\Database;

/**
 * Minimal QueryBuilder 
 */
class Query {

  /**
   * Active PDO connection shared by queries.
   * @var PDO
   */
  protected PDO $db;

  /**
   * Target table for the current query.
   * @var string
   */
  private string $table = '';

  /**
   * Collected WHERE clauses for the next statement.
   * @var array
   */
  private array $wheres = [];

  /**
   * Create a new query builder for the given table.
   *
   * @param string $table
   */
  public function __construct(string $table) {
    $this->db = Database::getConnection();
    $this->table = $table;
  }

  /**
   * Factory helper to start a query for a table.
   *
   * @param string $name
   * @return self
   */
  public static function table(string $name) : self {
    return new Query($name);
  }

  /**
   * Insert a new row and return the inserted id.
   *
   * @param array<string,mixed> $data
   * @return int
   */
  public function insert(array $data) {
    $placeholders = array_map(fn($key) => ':' . $key, array_keys($data));

    $sql = 'INSERT INTO ' . $this->table . ' (';
    $sql .= implode(', ', array_keys($data)); 
    $sql .= ') VALUES (';
    $sql .= implode(', ', $placeholders);
    $sql .= ')';    
    $stmt = $this->db->prepare($sql);
    foreach ($data as $key => $value) {
      $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();

    $this->wheres = [];
    return (int)$this->db->lastInsertId();
  }

  /**
   * Update rows matching current WHERE clauses.
   *
   * @param array<string,mixed> $data
   * @return int
   * @throws \RuntimeException
   */
  public function update(array $data) : int 
  {
    if (empty($this->wheres)) {
      throw new \RuntimeException('Refusing to UPDATE without WHERE clause.');
    }

    if (empty($data)) {
      throw new \InvalidArgumentException('Update data cannot be empty.');
    }

    foreach ($data as $key => $value) {
      // keys must be string column names
      if (!is_string($key) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
        throw new \InvalidArgumentException("Invalid column name in update: {$key}");
      }

      // keep it simple: only bind scalars or null
      if (!(is_scalar($value) || $value === null)) {
        throw new \InvalidArgumentException("Invalid value type for '{$key}'. Only scalar/null allowed.");
      }
    }

    $setClauses = array_map(fn($key) => $key . ' = :' . $key, array_keys($data));

    $sql = 'UPDATE ' . $this->table . ' SET ';
    $sql .= implode(', ', $setClauses) . ' ';
    $sql .= $this->buildWhereClause();

    $stmt = $this->db->prepare($sql);

    foreach ($data as $key => $value) {
      $stmt->bindValue(':' . $key, $value);
    }

    foreach ($this->wheres as $where) {
      $stmt->bindValue(':' . $where['column'], $where['value']);
    }
    $stmt->execute();
    
    $this->wheres = [];
    return $stmt->rowCount();
  }

  /**
   * Add a WHERE condition to the query.
   *
   * @param string $column
   * @param string $operator
   * @param mixed $value
   * @return self
   */
  public function where(string $column, string $operator, $value ) : self 
  {
    $operator = strtoupper(trim($operator));
    $allowed = [
      '=', '!=', '<>',
      '>', '>=', '<', '<=',
      'LIKE', 'NOT LIKE',
    ];

    if (!in_array($operator, $allowed, true)) {
      throw new \InvalidArgumentException("Invalid operator: {$operator}");
    }

    $this->wheres[] = [
      'column' => $column, 
      'value' => $value, 
      'operator' => $operator
    ];
    
    return $this;
  }

  /**
   * Fetch the first matching row or null.
   *
   * @return array|null
   */
  public function first() : ?array {
    $sql = 'SELECT * FROM ' . $this->table . ' ' . $this->buildWhereClause() . ' LIMIT 1';
    $stmt = $this->db->prepare($sql);
    foreach ($this->wheres as $where) {
      $stmt->bindValue(':' . $where['column'], $where['value']);
    }
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $this->wheres = [];

    return $result ?: null;
  }

  /**
   * Fetch matching rows.
   *
   * @param int|null $limit  null = no limit
   * @param int $offset
   * @param string $orderBy
   * @param string $direction
   * @return array
   */
  public function get(
      ?int $limit = null,
      int $offset = 0,
      string $orderBy = 'id',
      string $direction = 'ASC'
  ): array {
    $offset = max(0, $offset);

    // very basic hardening for ORDER BY (since it can't be binded in identifiers)
    $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $orderBy)) {
      throw new \InvalidArgumentException('Invalid orderBy column');
    }

    $sql = 'SELECT * FROM ' . $this->table . ' ' . $this->buildWhereClause();
    $sql .= ' ORDER BY ' . $orderBy . ' ' . $direction;

    if ($limit !== null) {
      $limit = max(1, $limit);
      $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    }

    $stmt = $this->db->prepare($sql);

    foreach ($this->wheres as $where) {
      $stmt->bindValue(':' . $where['column'], $where['value']);
    }

    $stmt->execute();
    $this->wheres = [];

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }


  /**
   * Build the WHERE clause for the current query.
   *
   * @return string
   */
  private function buildWhereClause() : string {
    if (empty($this->wheres)) {
      return '';
    }

    $clauses = array_map(function($where) {
      return $where['column'] . ' ' . $where['operator'] . ' :' . $where['column'];
    }, $this->wheres);

    return 'WHERE ' . implode(' AND ', $clauses);
  }

  /**
   * Expose the underlying PDO connection.
   *
   * @return PDO
   */
  public function pdo(): PDO
  {
    return $this->db;
  }
}
