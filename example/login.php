<?php

use eftec\DaoOne;
use eftec\SecurityOneMysql;
include "autoload.php";

$conn=new DaoOne("127.0.0.1","root","abc.123","securitytest","log.txt"); //CREATE SCHEMA `securitytest` ;




try {
    $conn->connect();

} catch (Exception $e) {
    die("Error :".$e->getMessage());
}

$sec=new SecurityOneMysql($conn,"../lib/");
$sec->initPage="frontpage.php";

$sec->validate();

//$sec->createTables();

$sec->loginScreen("It is a login screen","you should <a href='1.initialize.php'>1.initialize.php</a> first. then the user is admin/admin or user/user");

