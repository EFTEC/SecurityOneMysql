<?php
use eftec\PdoOne;
use eftec\SecurityOneMysql;

include "../vendor/autoload.php";

echo "<h1>Initializing. Some errors are ok</h1>";

$conn=new PdoOne('mysql',"127.0.0.1","root","abc.123","securitytest","log.txt"); //CREATE SCHEMA `securitytest` ;
$conn->logLevel=2;
try {
    $conn->connect();

} catch (Exception $e) {
    die("Error :".$e->getMessage());
}

$sec=new SecurityOneMysql($conn,"../lib/");
var_dump($sec->createTables());

try {
    $sec->addUser(["iduser" => 1
        , "user" => "admin"
        , "password" =>"admin"
        , "fullname" => "John Doe"
        , "email" => "johndoe@email.com"
        , "status" => 1
        , "role"=>"admin"]);
    $sec->addUser(["iduser"=>2
        ,"user"=>"user"
        ,"password"=>"user"
        ,"fullname"=>"Anna Smith"
        ,"email"=>"AnnaSmith@email.com"
        ,"phone"=>"111"
        , "status" => 1
        ,"address"=>"Sunset"
        , "role"=>"user"]);
} catch (Exception $e) {
    echo "Note: Insert ommited ".$e->getMessage()."<br>";
}
// group
try {
    $sec->addGroup(['idgroup'=>1,'name'=>'user']);
    $sec->addGroup(['idgroup'=>2,'name'=>'admin']);
    $sec->addGroup(['idgroup'=>3,'name'=>'sysop']);
} catch (Exception $e) {
    echo "Note: Group not created ".$e->getMessage()."<br>";
}
// userxgroup
try {
    $conn->set(['iduser'=>1,'idgroup'=>1])->from($sec->tableUserXGroup)->insert();
    $conn->set(['iduser'=>1,'idgroup'=>2])->from($sec->tableUserXGroup)->insert();
    $conn->set(['iduser'=>2,'idgroup'=>1])->from($sec->tableUserXGroup)->insert();
} catch (Exception $e) {
    echo "Note: userxgroup not created ".$e->getMessage()."<br>";
}
