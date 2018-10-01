<?php

use eftec\bladeone\BladeOne;
use eftec\DaoOne;
use eftec\SecurityOneMysql;

include "autoload.php";


$blade=new BladeOne("../lib/view","../lib/compile",BladeOne::MODE_AUTO);

echo $blade->run("registerok",['title'=>111
    , 'subtitle'=>222
    , 'logo'=>'icons/mailing.svg'
    , 'email'=>'aaa@aaa.com']);