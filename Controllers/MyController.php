<?php
namespace App\Controllers;
use PDO;
use PDOException;
use App\includes\Database;
use Exception;
class Mycontroller
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

   
}
