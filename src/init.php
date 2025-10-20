<?php
session_start();
require_once '../../private/src/Config/Database.php';

$database = new Database();
$pdo = $database->getConnection();