<?php

use eftec\DaoOne;
use eftec\SecurityOneMysql;
include "../vendor/phpmailer/phpmailer/src/PHPMailer.php";
include "../vendor/phpmailer/phpmailer/src/SMTP.php";
include "../vendor/eftec/securityone/lib/SecurityOne.php";
include "../vendor/eftec/bladeone/lib/BladeOne.php";
include "../vendor/eftec/daoone/lib/DaoOne.php";
include "../lib/SecurityOneMysql.php";

$conn=new DaoOne("127.0.0.1","root","abc.123","securitytest","log.txt"); //CREATE SCHEMA `securitytest` ;



try {
    $conn->connect();

} catch (Exception $e) {
    die("Error :".$e->getMessage());
}

$sec=new SecurityOneMysql($conn,"../lib/");
$sec->whiteList[]='changepassword.php';
$sec->initPage="frontpage.php";

$sec->validate();

//$sec->createTables();

$sec->changePasswordScreen("It is a login screen"
    ,"you could change <a href='https://github.com/EFTEC/SecurityOneMysql'>this</a>"
    ,"icons/safe.png","icons/unsafe.png");

