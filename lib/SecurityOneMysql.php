<?php

namespace eftec;


use DateTime;
use eftec\bladeone\BladeOne;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Class SecurityOneMysql
 * This class manages the security.
 * @version 0.11 20180930
 * @package eftec
 * @author Jorge Castro Castillo
 * @copyright (c) Jorge Castro C. MIT License  https://github.com/EFTEC/SecurityOneMysql
 * @see https://github.com/EFTEC/SecurityOneMysql
 */
class SecurityOneMysql extends SecurityOne
{
    // nullval is used when you want to mark a value as not-selected (null) but you can't do it using string "".
    const NULLVAL = '__NULLVAL__';
    /** @var DaoOne */
    var $conn;
    /** @var ValidationOne */
    var $val;
    /** @var ErrorList */
    var $errorList;
    /** @var PHPMailer */
    var $emailServer;


    /** @var string it is where the template will be located. By default it searhces in the /lib folder */
    var $templateRoot="";

    public $loginPage="login.php";
    public $logoutPage="logout.php";
    public $initPage="init.php";

    public $registerPage="register.php"; // create new account and send an email
    public $activatePage="activate.php"; // the email points here.
    public $recoverPage="recoverPassword.php"; //send me a new password.
    public $changePage="changePassword.php"; //Change the password (after validation).


    /** @var array pages that are whitelisted (you could access without a session) */
    var $whiteList=["login.php","logout.php","register.php","activate.php"
        ,"recoverPassword.php","changePassword.php"];

    var $tableUser="sec_user";
    var $tableGroup="sec_group";
    var $tableUserXGroup="sec_userxgroup";
    var $tableActivation="sec_activation";
    /** @var string you can disable cookies by setting this table to empty */
    var $tableCookie="sec_usercookie";
    /** @var array  ['user','password','name','smtpserver','smtpport','from','fromname','reply','replyname'] */
    private $emailConfig=[];


    /**
     * SecurityOneMysql constructor.
     * @param DaoOne $conn
     * @param string $templateRoot
     * @param array $emailConfig ['user','password','name','smtpserver','smtpport']
     */
    public function __construct($conn=null,$templateRoot=null,$emailConfig=null)
    {
        // injecting
        if ($conn===null) {
            if (function_exists('getDb')) {
                $this->conn=getDb();
            } else {
                trigger_error("You must set a database");
            }
        } else {
            $this->conn=$conn;
        }
        
        if (!$emailConfig) {
            if (function_exists('getEmail')) {
                // its injected
                $this->email=getEmail();
            } else {
                // it's created with constants (if any)
                $this->emailConfig=['user'=>EFTEC_EMAIL_USER,
                    'password'=>EFTEC_EMAIL_PASSWORD,
                    'smtpserver'=>EFTEC_EMAIL_SMPTSERVER
                    ,'smtpport'=>EFTEC_EMAIL_SMPTPORT
                    ,'from'=>EFTEC_EMAIL_FROM
                    ,'fromname'=>EFTEC_EMAIL_FROMNAME
                    ];
                if (defined(EFTEC_EMAIL_REPLY)) {
                    $this->emailConfig['reply']=EFTEC_EMAIL_REPLY;
                    $this->emailConfig['replyname']=EFTEC_EMAIL_REPLYNAME;
                }
                $this->createEmailServer();
            }
        } else {
            // its created with parameters.
            $this->emailConfig=$emailConfig;
            $this->createEmailServer();
        }

        $this->templateRoot=($templateRoot===null)?dirname(__FILE__):$templateRoot;
        parent::__construct(true); // if the session exists then it's logged.

        $this->setLoginFn(function(SecurityOneMysql $sec) {
            return $sec->getUserFromDB($sec->user,null,$sec->password);
        });

        $this->setStoreCookieFn(function (SecurityOne $sec) {
            $this->conn->set(['iduser'=>$sec->iduser,'cookie'=>$sec->cookieID])
                ->from($this->tableCookie)
                ->insert();
            // garbarge collector, we delete all expired cookies.
            $this->conn->from($this->tableCookie)
                ->where("datecreated < DATE_SUB(NOW(),INTERVAL 1 YEAR)")
                ->delete();
        });

        $this->setGetStoreCookieFn(function (SecurityOne $sec) {
            $idUser=$this->conn->select("iduser")
                ->from($this->tableCookie)
                ->where(['cookie'=>$sec->cookieID])
                ->firstScalar();
            if ($idUser==null) {
                // the cookie exists  but it's not associate with a right user (user delete or cookie expired)
                // so, we delete the cookie
                unset($_COOKIE['phpcookiesess']);
                setcookie('phpcookiesess', null, -1, '/');
                return null;
            }
            return $idUser;
        });
        // injecting
        if (function_exists('getVal')) {
            $this->val=getVal();
        } else {

            $this->val=new ValidationOne();
        }
        // injecting
        if (function_exists('getErrorList')) {
            $this->errorList=getErrorList();
        } else {
            $this->errorList=new ErrorList();
        }

    }
    private function createEmailServer() {
        $this->emailServer = new PHPMailer;

        //Tell PHPMailer to use SMTP
        $this->emailServer->isSMTP();
        //Enable SMTP debugging
        // 0 = off (for production use)
        // 1 = client messages
        // 2 = client and server messages
        $this->emailServer->SMTPDebug = 1;
        //Set the hostname of the mail server
        $this->emailServer->Host = $this->emailConfig['smtpserver']; // 'smtp.gmail.com'
        // use
        // $this->emailServer->Host = gethostbyname('smtp.gmail.com');
        // if your network does not support SMTP over IPv6
        //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
        $this->emailServer->Port = $this->emailConfig['smtpport']; // 587
        //Set the encryption system to use - ssl (deprecated) or tls
        $this->emailServer->SMTPSecure = 'tls';
        //Whether to use SMTP authentication
        $this->emailServer->SMTPAuth = true;
        //Username to use for SMTP authentication - use full email address for gmail
        $this->emailServer->Username = $this->emailConfig['user'];
        //Password to use for SMTP authentication
        $this->emailServer->Password = $this->emailConfig['password'];
    }

    /**
     * @param $to
     * @param $toName
     * @param $title
     * @param $msg
     * @param string $msgplain
     * @return bool
     * @throws Exception
     */
    public function sendMail($to,$toName, $title,$msg,$msgplain="") {


        //Set who the message is to be sent from
        $this->emailServer->setFrom($this->emailConfig['from'], $this->emailConfig['fromname']);
        //Set an alternative reply-to address
        if (isset($this->emailConfig['reply'])) {
            $this->emailServer->addReplyTo($this->emailConfig['reply'], $this->emailConfig['replyname']);
        }
        //Set who the message is to be sent to
        $this->emailServer->addAddress($to,$toName);
        //Set the subject line
        $this->emailServer->Subject = $title;
        //Read an HTML message body from an external file, convert referenced images to embedded,
        //convert HTML into a basic plain-text alternative body
        $this->emailServer->msgHTML($msg, __DIR__);
        //Replace the plain text body with one created manually
        $this->emailServer->AltBody = $msgplain;
        //Attach an image file
        //$this->emailServer->addAttachment('images/phpmailer_mini.png');
        //send the message, check for errors
        echo "sending<br>";
        if (!$this->emailServer->send()) {
            throw new Exception("Mailer Error: " . $this->emailServer->ErrorInfo);
        } else {
            echo "sending ok<br>";
            return true;
        }
    }

    /**
     * @param $user
     * @param null $idUser
     * @param null $password
     * @param null $email
     * @return bool
     * @throws \Exception
     */
    private function getUserFromDB($user=null,$idUser=null, $password=null,$email=null) {
        // load the user from the database
        $this->conn->select("*")
            ->from($this->tableUser);
        if ($idUser!==null) $this->conn->where(['iduser'=>$idUser]);
        if ($user!==null) $this->conn->where(['user'=>$user]);
        if ($password!==null) $this->conn->where(['password'=>$password]);
        if ($email!==null) $this->conn->where(['email'=>$email]);
        try {
            $user = $this->conn->first();
        } catch(\Exception $e) {
            $this->conn->throwError($e->getMessage());
        }
        if (empty($user)) {
            return false;
        } else {
            // load the groups (if any)
            try {
                $userxGroup = $this->conn->select("r.name")
                    ->from($this->tableUserXGroup." ur")
                    ->join($this->tableGroup." r on ur.idgroup=r.idgroup")
                    ->where("ur.iduser=?", ['i', @$user['iduser']])
                    ->toList();
            } catch (\Exception $e) {
                $this->conn->throwError($e->getMessage());
                return false;
            }
            $groups=[];
            foreach($userxGroup as $tmp) {
                $groups[]=$tmp['name'];
            }
            $this->factoryUser($user['user'],$user['password'],$user['fullname'],$groups,$user['role'],$user['status'],$user['email'],$user['iduser']);
            return true;
        }
    }
    /**
     * @param $idActivation
     * @return array|bool
     * @throws \Exception
     */
    private function getActivateFromDB($idActivation) {
        // load the user from the database
        $this->conn->select("*")
            ->from($this->tableActivation);
        $this->conn->where(['idactivation'=>$idActivation]);
        try {
            $act = $this->conn->first();
        } catch(\Exception $e) {
            $this->conn->throwError($e->getMessage());
        }
        if (empty($act)) {
            return false;
        } else {
            return $act;
        }
    }
    // required('this field is required')::minleght(20)::maxlenght(20)::get('field')

    //<editor-fold desc="database objects">

    /**
     * @param array $user ['user'=>'name','password'=>'pwd','role'=>'AAA','fullname'=>'fullname','email'=>'email']
     * @param null|int $idGroup
     * @return mixed  Returns the id of the new user.
     * @throws \Exception
     */
    public function addUser($user, $idGroup=null) {
        if ($idGroup==null) return $this->addUserOnly($user);
        try {
            $this->conn->startTransaction();
            $idUser = $this->addUserOnly($user);
            $this->addUserxGroup($idUser,$idGroup);
            $this->conn->commit();
        } catch (\Exception $e) {
            if ($this->conn->transactionOpen) $this->conn->rollback();
            throw $e;
        }
        return $idUser;
    }

    /**
     * @param array $user ['user'=>'name','password'=>'pwd','role'=>'AAA','fullname'=>'fullname','email'=>'email']
     * @return mixed
     * @throws \Exception
     */
    private function addUserOnly($user) {
        $oldPwd=$user['password']; // backup the password without encryption
        $user['password']=$this->encrypt($user['password']);
        $r=$this->conn->set($user)
            ->from($this->tableUser)
            ->insert();
        $user['password']=$oldPwd; // recover the backup.
        return $r;
    }

    /**
     * @param array $group ['idgroup'=>20,'name'=>'groupname']
     * @return mixed
     * @throws \Exception
     */
    public function addGroup($group) {
        return $this->conn->set($group)->from($this->tableGroup)->insert();
    }

    /**
     * @param $iduser
     * @param $idgroup
     * @return mixed
     * @throws \Exception
     */
    public function addUserxGroup($iduser,$idgroup) {

        return $this->conn->set(['iduser'=>$iduser,'idgroup'=>$idgroup])->from($this->tableUserXGroup)->insert();
    }
    //</editor-fold>

    /**
     * Back to the login page
     */
    public function backLogin() {
        session_write_close();
        @header("location:" . $this->loginPage.'?returnUrl='.$this->safeReturnUrl(@$_SERVER['REQUEST_URI'],$this->initPage));
        die(1);
    }


    /**
     * Validate if the user is logged.
     * If it is not logged and it's not in the login page, then it's redirected to the login page
     * If it is logged and it's in the login page, then it's redirected to the frontpage
     * @param bool $useView
     */
    public function validate($useView=false) {
        $currFile=basename($_SERVER['PHP_SELF']);
        $inLoginPage=($currFile==$this->loginPage);

        if (!$this->isLogged) {
            $idUser = $this->getStoreCookie();
            if ($idUser) {
                // autologin  via cookie
                try {
                    $this->getUserFromDB(null, $idUser, null);
                    $this->fixSession(false);
                } catch (\Exception $e) {
                    $this->isLogged = false;
                }
            }
        }
        if ($this->isLogged && $inLoginPage) {
            // it's logged then redirect to the init page.
            session_write_close();
            $returnUrl=$this->safeReturnUrl(@$_REQUEST['returnUrl'],$this->initPage);
            header("location:".$returnUrl);
            die(1);
        }
        if (!in_array($currFile,$this->whiteList) && !$this->isLogged) {
            $this->backLogin();
        }

    }

    /**
     * Delete a cookie using the current cookieid
     * @throws \Exception
     */
    public function deleteCookie() {
        $this->conn->from($this->tableCookie)->where(['cookie'=>$this->cookieID])->delete();
    }

    /**
     * Update a cookie using the current cookieid
     * @throws \Exception
     */
    public function updateCookie() {
        $this->conn->from($this->tableCookie)->set('datecreated=now()',[])->where(['cookie'=>$this->cookieID])->update();
    }


    /**
     * Update a cookie using the current cookieid
     * @param $iduser
     * @param $status
     * @throws \Exception
     */
    public function userChangeStatus($iduser,$status) {
        $this->conn->from($this->tableUser)
            ->set(['status'=>$status])->where(['iduser'=>$iduser])->update();
    }

    /**
     * Update a cookie using the current cookieid
     * @param $iduser
     * @param $password
     * @throws \Exception
     */
    public function userChangePassword($iduser,$password) {
        $pwd=$this->encrypt($password);
        $this->conn->from($this->tableUser)
            ->set(['password'=>$pwd])->where(['iduser'=>$iduser])->update();
    }

    /**
     * it returns the url if the url is local and safe. Otherwise, it returns the default value.
     * @param $url
     * @param $default
     * @return mixed
     */
    protected function safeReturnUrl($url,$default) {
        if ($url==null) return $default;
        return preg_match('#^\/\w+#',$url)?$url:$default;
    }

    public function validateUser($us) {
        try {
            if ($this->errorList->errorcount==0) {
                $load = $this->getUserFromDB($us['user']);
                if ($load!==false) {
                    $this->errorList->addItem('user','User already exist');
                    $load=$this->getUserFromDB(null,null,null,$us['email']);
                    if ($load!==false) $this->errorList->addItem('email','Email already exist');
                }
            }
        } catch (\Exception $e) {
            $this->errorList->addItem('user','It\'s not possible to validate user');
        }
    }
    protected function generateRandomString($length = 7) {
        // int 2147483647 mysql limit
        //      562383523 generateRandomString(7);
        $characters = '0123456789';
        $randomString = date("m")+date("d")+date("y"); //Ymdhis
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    /**
     * @param $uid
     * @param $idUser
     * @param $status
     * @throws \Exception
     */
    public function addActivation($uid,$idUser,$status) {
        $this->conn->insert($this->tableActivation
            ,['idactivation','i','iduser','i','status','i']
            ,[$uid,$idUser,$status]);
    }

    /**
     * @param $uid
     * @param $idUser
     * @param $status
     * @throws \Exception
     */
    public function deleteActivation($uid) {
        $this->conn->delete($this->tableActivation,['idactivation','i',$uid]);
    }

    /**
     * @return BladeOne
     */
    protected  function getBlade() {

        if (function_exists("getBlade")) {

            $blade=getBlade($this->templateRoot."/view",$this->templateRoot."/compile"); // we inject (if any)
        } else {
            $blade=new BladeOne($this->templateRoot."/view",$this->templateRoot."/compile",BladeOne::MODE_AUTO);
        }
        return $blade;
    }

    public function registerScreen($title="Register Screen",$subtitle="",$logo="https://avatars3.githubusercontent.com/u/19829219?s=200&v=4") {
        $blade=$this->getBlade();


        $button=$this->val->type('string')->post('button');
        $message="";
        $logged=false;

        $user=[];

        if ($button) {
            $user=['user'=>$this->val->type('string')
                ->condition('betweenlen','The %field must have a length between %first and %second',[3,45])
                ->post('user','Usuario incorrecto')
                ,'password'=>$this->val->type('string')
                    ->condition('betweenlen',"The %field must have a length between %first and %second",[3,64])
                    ->post('password')
                ,'password2'=>$this->val->type('string')
                    ->condition('betweenlen',"The %field must have a length between %first and %second",[3,64])
                    ->post('password2')
                ,'role'=>'customer'
                ,'fullname'=>$this->val->type('string')
                    ->condition('betweenlen',"The %field must have a length between %first and %second",[3,128])
                    ->post('fullname')
                ,'email'=>$this->val->type('string')
                    ->condition('minlen',null,3)
                    ->condition('maxlen',null,45)->post('email')];
            $this->val->type('string')->condition('eq','The passwords aren\'t equals',$user['password'])
                ->set($user['password2'],'password2');
            $this->validateUser($user);

            //$this->errorList->get(1)->firstError();
            if ($this->errorList->errorcount) {
                $message="User or password incorrect";
                $button=false;
            } else {
                unset($user['password2']);
                $user['status']=0; // not active
                try {
                    $message="Unable to create user or activation";
                    $this->conn->startTransaction();
                    $idUser = $this->addUser($user);
                    $uid=$this->generateRandomString();
                    // we add an activation
                    $this->addActivation($uid,$idUser,1);
                    $msg="It is the activation code $uid";
                    $message="Unable to send email";
                    $this->sendMail($user['email'], $user['fullname'], "Activation Code", $msg);
                    $this->conn->commit(false);
                } catch (\Exception $e) {
                    $this->conn->rollback(false);
                    $button=false;
                }

                if ($button) {
                    echo $blade->run("registerok", ['title' => $title
                        , 'subtitle' => $subtitle
                        , 'logo' => $logo
                        , 'error'=>$this->errorList
                        , 'message' => $message
                        , 'email' => $user['email']]);
                    die(1);
                }
            }
        }
        if (!$button) {
            $returnUrl=$this->safeReturnUrl(@$_REQUEST['returnUrl'],$this->initPage);
            try {
                echo $blade->run("register", [
                    'obj'=>$user
                    , 'returnUrl' => $returnUrl
                    , 'message' => $message
                    , 'error'=>$this->errorList
                    , 'title'=>$title
                    , 'subtitle'=>$subtitle
                    , 'logo'=>$logo]);
            } catch (\Exception $e) {
                echo "error showing register page";
            }
        } else {
            $returnUrl=$this->safeReturnUrl(@$_REQUEST['returnUrl'],$this->initPage);
            session_write_close();
            header("location:".$returnUrl);
            die(1);
        }
    }
    public function recoverScreen($title="Register Screen",$subtitle="",$icon="",$iconemail="") {

        $blade=$this->getBlade();

        $button=$this->val->type('string')->post('button');
        $message="";
        $logged=false;
        $user=[];
        switch ($button) {
            case 'user':
                $user=$this->val->type('string')->condition('maxlen',null,45)->post('user');

                if (!$user) {
                    $message="Debe ingresar un usuario";
                    $button=false;
                } else {
                    try {
                        $us = $this->getUserFromDB($user, null, null, null);
                    } catch (\Exception $e) {
                        $message="Usuario no se puede leer";
                        $button=false;
                    }
                    if ($us===false) {
                        $message="Usuario no encontrado";
                        $button=false;
                    }
                }

                break;
            case 'email':
                $email=$this->val->type('string')->post('email');

                if (!$email) {
                    $message="Debe ingresar un correo";
                    $button=false;
                } else {
                    try {
                        $us = $this->getUserFromDB(null, null, null, $email);
                    } catch (\Exception $e) {
                        $message="Correo no se puede leer";
                        $button=false;
                    }
                    if ($us===false) {
                        $message="Email no encontrado";
                        $button=false;
                    }
                }
                break;
            default:
        }
        if ($button) {
            try {
                $idUser = $this->iduser;
                $uid=$this->generateRandomString();
                // we add an activation
                $this->addActivation($uid, $idUser, 2);
            } catch (\Exception $e) {
                $message="Unable to create user or activation ".$e->getMessage();
                $button=false;
            }
            if ($button) {
                $msg = "It is the activation code $uid";
                try {
                    $this->sendMail($this->email, $this->name, "Activation Code", $msg);
                } catch (Exception $e) {
                    $message = "We are unable to send an email, active it here";
                }
                if ($button) {
                    echo $blade->run("recoversend", ['title' => $title
                        , 'subtitle' => $subtitle
                        , 'logo' => $iconemail
                        , 'message' => $message
                        , 'email' => $this->email]);
                    die(1);
                }
            }
        }
        if (!$button) {
            $returnUrl=$this->safeReturnUrl(@$_REQUEST['returnUrl'],$this->initPage);
            try {
                echo $blade->run("recover", [
                    'obj'=>$user
                    , 'returnUrl' => $returnUrl
                    , 'message' => $message
                    , 'title'=>$title
                    , 'error'=>$this->errorList
                    , 'subtitle'=>$subtitle
                    , 'logo'=>$icon]);
            } catch (\Exception $e) {
                echo "error showing register page";
            }
        }


    }

    public function activeScreen($title="Register Screen",$subtitle="",$iconOK="",$iconFail="") {

        $blade=$this->getBlade();

        $id=$this->val->type('integer')->default(0)->ifFailThenDefault()->get('id');
        $icon=$iconFail;
        $message="";
        try {
            $activate = $this->getActivateFromDB($id);
        } catch (\Exception $e) {
            $activate=false;
            $message="Unable to activate user";
        }
        if ($message=="") {
            if ($activate !== false) {

                try {

                    $this->userChangeStatus($activate['iduser'], 1);
                    $message = "Usuario activado. Presione aqui para ir a usuario";
                    $icon=$iconOK;

                } catch (\Exception $e) {
                    $message="Unable to change status ".$e->getMessage();
                }
                @$this->deleteActivation($id);
            } else {
                $message = "Codigo incorrecto";
            }
        }

        try {
            echo $blade->run("activateok", [
                'message' => $message
                , 'title'=>$title
                , 'subtitle'=>$subtitle
                , 'logo'=>$icon]);
        } catch (\Exception $e) {
            echo "error showing register page";
        }
    }

    public function changePasswordScreen($title="Change Password Screen",$subtitle="",$iconOK="",$iconFail="") {

        $blade=$this->getBlade();

        $id=$this->val->type('integer')->default(0)->ifFailThenDefault()->get('id');
        $icon=$iconFail;
        $message="";
        $valid=true;
        $user="";
        try {
            $activate = $this->getActivateFromDB($id);
        } catch (\Exception $e) {
            $activate=false;
            $message="Unable Change password";
        }
        if ($activate===false) {
            $message="Code incorrect";
            $valid=false;
        }

        if ($message=="") {
            $idUser=$activate['iduser'];
            $r=$this->getUserFromDB(null,$idUser);
            if ($r===false) {
                $message="Usuario no encontrado";
                $valid=false;
            } else {
                $user=$this->user;
            }
            $icon=$iconOK;
            $button=$this->val->type('string')->post('button','boolean');
            if ($button) {
                $password=$this->val->type('string')->condition('maxlen',null,64)->post('password');
                $password2=$this->val->type('string')->condition('maxlen',null,64)->post('password2');
                if ($password!=$password2) {
                    $message="Password Incorrect";
                } else {
                    try {
                        $this->userChangePassword($idUser, $password);
                        $message="Password changed";
                        $valid=false;
                        @$this->deleteActivation($id);
                    } catch (\Exception $e) {
                        $message="Unable to change password";
                    }
                }

            }
        }

        try {
            echo $blade->run("newpassword", [
                'message' => $message
                , 'title'=>$title
                , 'subtitle'=>$subtitle
                , 'valid'=>$valid
                , 'home'=>$this->loginPage
                , 'error'=>$this->errorList
                , 'user'=>$user
                , 'logo'=>$icon]);
        } catch (\Exception $e) {
            echo "error showing register page";
        }
    }
    public function logoutScreen($useView=false) {
        if ($_COOKIE['phpcookiesess']) {
            $this->cookieID=$_COOKIE['phpcookiesess'];
            @$this->deleteCookie();
        }
        $this->logout();
        session_write_close();
        @header("location:".$this->logoutPage);
        exit(1);
    }
    public function createTables() {
        $msgError=[];
        try {
            $sql= /** @lang text */
                "CREATE TABLE `{$this->tableUser}` (
            `iduser` INT NOT NULL,
            `user` VARCHAR(45) NOT NULL,
            `password` VARCHAR(64) NOT NULL,
            `role` VARCHAR(64) NOT NULL,
            `status` int NOT NULL default 1,
            `fullname` VARCHAR(128) NOT NULL,
            `email` VARCHAR(45) NOT NULL,
            `phone` VARCHAR(45) NULL,
            `address` VARCHAR(45)  NULL,            
            PRIMARY KEY (`iduser`));
            ALTER TABLE `{$this->tableUser}` 
            ADD UNIQUE INDEX `{$this->tableUser}_key1` (`user` ASC) VISIBLE;
            ;";

            $this->conn->runMultipleRawQuery($sql, true);
        } catch (\Exception $e) {
            $msgError[]= "Note: Table {$this->tableUser} not created (maybe it exists) ".$e->getMessage()."<br>";
        }
        if ($this->tableCookie) {
            try {
                $sql = /** @lang text */
                    "CREATE TABLE `{$this->tableCookie}` (
                `idcookie` INT NOT NULL auto_increment,
                `iduser` int not null,
                `cookie` VARCHAR(64) NOT NULL,
                `datecreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`idcookie`));
                ALTER TABLE `{$this->tableCookie}` 
                ADD INDEX `{$this->tableCookie}_key1` (`datecreated` ASC) VISIBLE;
                ALTER TABLE `{$this->tableCookie}` 
                ADD INDEX `{$this->tableCookie}_key2` (`cookie` ASC) VISIBLE;                
                ;";
                $this->conn->runMultipleRawQuery($sql, true);
            } catch (\Exception $e) {
                $msgError[]= "Note: Table {$this->tableCookie} not created (maybe it exists) ".$e->getMessage()."<br>";
            }
        }

        try {
            $sql= /** @lang text */
                "CREATE TABLE `{$this->tableGroup}` (
                `idgroup` INT NOT NULL,
                `name` VARCHAR(45) NOT NULL,
                PRIMARY KEY (`idgroup`));
                ALTER TABLE ``{$this->tableGroup}`` 
                ADD UNIQUE INDEX ``{$this->tableGroup}`_key1` (`name` ASC) VISIBLE;";
            $this->conn->runMultipleRawQuery($sql, true);
        } catch (\Exception $e) {
            $msgError[]="Note: Table {$this->tableGroup} not created (maybe it exists) ".$e->getMessage()."<br>";
        }
        try {
            $sql= /** @lang text */
                "CREATE TABLE `{$this->tableUserXGroup}` (
                `iduser` INT NOT NULL,
                `idgroup` VARCHAR(45) NOT NULL,
                PRIMARY KEY (`iduser`, `idgroup`));
            ";
            $this->conn->runRawQuery($sql, array(), false);
        } catch (\Exception $e) {
            $msgError[]="Note: Table {$this->tableUserXGroup} not created (maybe it exists) ".$e->getMessage()."<br>";
        }

        try {
            $sql= /** @lang text */
                "CREATE TABLE `{$this->tableActivation}` (
            `idactivation` int NOT NULL,
            `iduser` int NOT NULL,
            `status` int NOT NULL,
            'date' DATETIME NULL DEFAULT CURRENT_TIMESTAMP,           
            PRIMARY KEY (`idactivation`));
            ;";

            $this->conn->runMultipleRawQuery($sql, true);
        } catch (\Exception $e) {
            $msgError[]= "Note: Table {$this->tableActivation} not created (maybe it exists) ".$e->getMessage()."<br>";
        }
        return $msgError;
    }


    public function loginScreen($title="Login Screen",$subtitle="",$logo="https://avatars3.githubusercontent.com/u/19829219?s=200&v=4") {
        $blade=$this->getBlade();

        $button=$button=$this->val->type('string')->post('button');
        $message="";
        $logged=false;
        if ($button) {
            $logged=$this->login($this->val->type('string')->condition('maxlen',null,45)->post('user')
                ,$this->val->type('string')->condition('maxlen',null,64)->post('password')
                ,(@$this->val->type('string')->post('remember',"boolean")=='1'));
            $message=(!$logged)?"User or password incorrect":"";
            if (($this->status == 0)) {
                $message = "User not active";
                $logged=false;
            }
        }
        if (!$button || !$logged) {
            $returnUrl=$this->safeReturnUrl(@$_REQUEST['returnUrl'],$this->initPage);
            try {
                echo $blade->run("login", ['user' => $this->user
                    , 'password' => ""
                    , 'returnUrl' => $returnUrl
                    , 'message' => $message
                    , 'title'=>$title
                    , 'subtitle'=>$subtitle
                    , 'logo'=>$logo
                    , 'useCookie'=>$this->useCookie]);
            } catch (\Exception $e) {
                echo "error showing login page";
            }
        } else {
            $returnUrl=$this->safeReturnUrl(@$_REQUEST['returnUrl'],$this->initPage);
            session_write_close();
            header("location:".$returnUrl);
            die(1);
        }
    }


}