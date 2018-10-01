<?php

use eftec\bladeone\BladeOne;
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
$sec->validate();
$obj=$sec->getCurrent(true);

$blade=new BladeOne("view","compile");

echo $blade->run("frontpage",['user'=>$obj['user'],'groups'=>$obj['group'],'name'=>$obj['name']]);

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