<?php
namespace App\Controllers;
use PDO;
use PDOException;
use App\includes\Database;
use Exception;
class UserController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    public function getUser(){
        $res=$this->db->query("SELECT * FROM users");
        $fetch= $res->fetchAll(PDO::FETCH_ASSOC);
        return $fetch;
    }
   
}
