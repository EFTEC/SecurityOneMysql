<?php
use eftec\PdoOne;
use eftec\SecurityOneMysql;

include "../vendor/autoload.php";
$conn=new PdoOne('mysql',"127.0.0.1","root","abc.123","securitytest","log.txt"); //CREATE SCHEMA `securitytest` ;

try {
    $conn->connect();
} catch (Exception $e) {
    die("Error :".$e->getMessage());
}

$sec=new SecurityOneMysql($conn,"../lib/");
$sec->logoutPage="login.php";
$sec->logoutScreen();

