<?php

use eftec\DaoOne;
use eftec\SecurityOneMysql;
include "autoload.php";

define('EFTEC_EMAIL_USER', 'sendme@email.com');
define('EFTEC_EMAIL_PASSWORD', 'email12345');
define('EFTEC_EMAIL_SMPTSERVER', 'smtp.gmail.com');
define('EFTEC_EMAIL_SMPTPORT', '587');
define('EFTEC_EMAIL_FROM', 'sendme@email.com');
define('EFTEC_EMAIL_FROMNAME', 'SecurityOne Send');
define('EFTEC_EMAIL_REPLY', 'sendme@email.com');
define('EFTEC_EMAIL_REPLYNAME', 'SecurityOne reply me');



$conn=new DaoOne("127.0.0.1","root","abc.123","securitytest","log.txt"); //CREATE SCHEMA `securitytest` ;



try {
    $conn->connect();

} catch (Exception $e) {
    die("Error :".$e->getMessage());
}

$sec=new SecurityOneMysql($conn,"../lib/");
$sec->whiteList[]='register.php';
$sec->initPage="frontpage.php";

$sec->validate();

//$sec->createTables();

$sec->registerScreen("It is a login screen","you could change <a href='https://github.com/EFTEC/SecurityOneMysql'>this</a>");

