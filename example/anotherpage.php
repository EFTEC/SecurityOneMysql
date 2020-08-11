<?php

use eftec\bladeone\BladeOne;
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
$sec->whiteList[]='anotherpage.php';
$sec->validate();
$obj=$sec->getCurrent(true);

$blade=new BladeOne("view","compile");

$blade->setAuth($sec->user,$sec->group[0],$sec->group); // integrate security with blade

if($obj===null) {
    echo $blade->run("anotherpage",['user'=>null,'groups'=>null,'name'=>null]);
} else {
    echo $blade->run("anotherpage",['user'=>$obj['user'],'groups'=>$obj['group'],'name'=>$obj['name']]);    
}



