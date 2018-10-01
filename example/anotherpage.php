<?php

use eftec\bladeone\BladeOne;
use eftec\DaoOne;
use eftec\SecurityOneMysql;

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
$sec->whiteList[]='anotherpage.php';
$sec->validate();
$obj=$sec->getCurrent(true);

$blade=new BladeOne("view","compile");

$blade->setAuth($sec->user,$sec->group[0],$sec->group); // integrate security with blade

echo $blade->run("anotherpage",['user'=>$obj['user'],'groups'=>$obj['group'],'name'=>$obj['name']]);


die(1);

?>
<h1>The user <?=$obj['user'];?> is logged</h1>
Groups:
<ul>
    <?php foreach($obj["group"] as $group) {
        echo "<li>$group</li>";
    }
    ?>
</ul>
<a href="login.php">login.php</a>
<a href="logout.php">logout.php</a>