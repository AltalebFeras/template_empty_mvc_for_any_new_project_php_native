<?php

namespace src\Abstracts;

use PDO;
use src\Services\Database;

/**
 * Abstract model of the repositories. Contains the following methods for all:
 * - getAll
 * - getById
 * - create
 * - updateById
 * - deleteById
 */
abstract class AbstractRepository
{

  protected $DB;
  private $model, $class, $table;

  public function __construct()
  {
    $database = new Database();
    $this->DB = $database->getDB();

    $this->model = get_class($this);
    $this->class = str_replace(['src\\Repositories\\', 'Repository'], '', $this->model);
    $this->table = strtolower($this->class) . 's';
  }

  /**
   * Method to retrieve all records from a given repository
   *
   * @return array<object> Returns an array of objects
   */
  public function getAll(): array
  {
    $sql = "SELECT * from $this->table;";
    $stmt = $this->DB->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_CLASS, "src\\Entities\\{$this->class}");
  }

  /**
   * Method to retrieve a record by its ID
   *
   * @param int $id The ID of the record to retrieve
   * @return object Returns an object of the specified class
   */
  
  public function getById(int $id): object|bool
  {
    $sql = "SELECT * from $this->table WHERE {$this->class}_id = :id;";
    $stmt = $this->DB->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    return $stmt->fetchObject("src\\Entities\\{$this->class}");
  }


  /**
   * Method to create a new record
   *
   * @param array $data The data to insert into the record
   * @return bool Returns true on success, false on failure
   */
  public function create(array $data): bool
  {
    $data = $this->prepareData($data); 
    $columns = implode(", ", array_keys($data));
    $placeholders = implode(", ", array_map(fn($key)=> ":$key", array_keys($data)));
    $sql = "INSERT INTO $this->table ($columns) VALUES ($placeholders);";
    $stmt = $this->DB->prepare($sql);
    foreach ($data as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    return $stmt->execute();
  }

  /**
   * Method to update a record by its ID
   *
   * @param int $id The ID of the record to update
   * @param array $data The data to update in the record
   * @return bool Returns true on success, false on failure
   */
  public function updateById(int $id, array $data): bool
  {
    $data = $this->prepareData($data);
    $setClause = implode(", ", array_map(fn($key) => "$key = :$key", array_keys($data)));
    $sql = "UPDATE $this->table SET $setClause WHERE {$this->class}_id = :id;";
    $stmt = $this->DB->prepare($sql);
    foreach ($data as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':id', $id);
    return $stmt->execute();
  }

    /**
   * Method to delete a record by its ID
   *
   * @param int $id The ID of the record to delete
   * @return bool Returns true on success, false on failure
   */
  public function deleteById(int $id): bool
  {
    $sql = "DELETE FROM $this->table WHERE {$this->class}_id = :id;";
    $stmt = $this->DB->prepare($sql);
    $stmt->bindParam(':id', $id);
    return $stmt->execute();
  }

  private function prepareData(array $data): array
  {
    foreach ($data as $key => $value) {
        if ($value instanceof \DateTime) {
            $data[$key] = $value->format('Y-m-d H:i:s');
        }
    }
    return $data;
  }
}
