<?php

namespace eftec;

use eftec\bladeone\BladeOne;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Class SecurityOneMysql
 * This class manages the security.
 * @version 1.5 20200125
 * @package eftec
 * @author Jorge Castro Castillo
 * @copyright (c) Jorge Castro C. MIT License  https://github.com/EFTEC/SecurityOneMysql
 * @see https://github.com/EFTEC/SecurityOneMysql
 */
class SecurityOneMysql extends SecurityOne
{
    // nullval is used when you want to mark a value as not-selected (null) but you can't do it using string "".
    const NULLVAL = '__NULLVAL__';

    var $debug=false;
    /** @var BladeOne */
    private $blade;

    /** @var PdoOne */
    var $conn;
    /** @var ValidationOne */
    var $val;
    /** @var MessageList */
    var $messageList;
    /** @var PHPMailer */
    var $emailServer;
    /** @var bool if it uses group or not. */
    var $hasGroup=true;
    var $defaultGroup=['user'];
    /** @var bool if it uses group or not. */
    var $hasRole=true;
    var $defaultRole='user';

    /** @var string it is where the template will be located. By default it searhces in the /lib folder */
    var $templateRoot="";

    public $loginPage="login.php";
    public $loginTemplate='login';
    public $logoutPage="logout.php";
    public $initPage="init.php";

    public $registerPage="register.php"; // create new account and send an email
    public $registerTemplate='register';
    public $registerOkTemplate='registerok';
    public $activatePage="activate.php"; // the email points here.
    public $recoverPage="recoverPassword.php"; //send me a new password.
    public $changePage="changePassword.php"; //Change the password (after validation).
    /** @var array It is the real name of the columns in the database */
    public $userMap=['iduser'=>'iduser'
        ,'user'=>'user'
        ,'password'=>'password'
        ,'role'=>'role'
        ,'status'=>'status'
        ,'fullname'=>'fullname'
        ,'email'=>'email'];

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
	 * @param PdoOne $conn
	 * @param string $templateRoot
	 * @param array $emailConfig ['user','password','name','smtpserver','smtpport']
	 * @param bool $hasGroup
	 * @param bool $hasRole
	 * @param bool $autoLogin
	 */
    public function __construct($conn=null,$templateRoot=null,$emailConfig=null
	    ,$hasGroup=true,$hasRole=true,$autoLogin=false)
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
        $this->hasGroup=$hasGroup;
        $this->hasRole=$hasRole;

        if (!$emailConfig) {
            if (function_exists('getEmail')) {
                // its injected
                $this->email=getEmail();
            } else {
                // it's created with constants (if any)
                if (@defined(EFTEC_EMAIL_SMPTSERVER)) {
                    $this->emailConfig = ['user' => @EFTEC_EMAIL_USER,
                        'password' => @EFTEC_EMAIL_PASSWORD,
                        'smtpserver' => @EFTEC_EMAIL_SMPTSERVER
                        , 'smtpport' => @EFTEC_EMAIL_SMPTPORT
                        , 'from' => @EFTEC_EMAIL_FROM
                        , 'fromname' => @EFTEC_EMAIL_FROMNAME
                    ];
                    if (defined(@EFTEC_EMAIL_REPLY)) {
                        $this->emailConfig['reply'] = EFTEC_EMAIL_REPLY;
                        $this->emailConfig['replyname'] = EFTEC_EMAIL_REPLYNAME;
                    }
                    $this->createEmailServer();
                } else {
                    // parameter is null, there is not a global function called getEmail() and constant EFTEC_EMAIL_SMPTSERVER is not defined.
                    // else, email service is disabled.
                    $this->emailServer=null;
                }
            }
        } else {
            // its created with parameters.
            $this->emailConfig=$emailConfig;
            $this->createEmailServer();
        }

        $this->templateRoot=($templateRoot===null)?dirname(__FILE__):$templateRoot;
        parent::__construct($autoLogin); // if the session exists then it's logged.

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
        if (function_exists('valid')) {
            $this->val=valid();
        } else {
            $this->val=new ValidationOne();
        }
        // injecting
        if (function_exists('messages')) {
            $this->messageList=messages();
        } else {
            $this->messageList=new MessageList();
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
        if (!$this->emailServer->send()) {
            throw new Exception("Mailer Error: " . $this->emailServer->ErrorInfo);
        } else {
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
        if ($this->hasRole) {
            $selectRole="`{$this->userMap['role']}` as `role`,";
        }else {
            $selectRole="";
        }
        $this->conn->select("`{$this->userMap['iduser']}` as `iduser`,
            `{$this->userMap['user']}` as `user`,
            `{$this->userMap['password']}` as `password`,
            $selectRole
            `{$this->userMap['status']}` as `status`,
            `{$this->userMap['fullname']}` as `fullname`,
            `{$this->userMap['email']}` as `email`");
        if (count($this->extraFields)) {
            foreach($this->extraFields as $key=>$value) {
                $this->conn->select(",`$key`");
            }
        }
        $this->conn->from($this->tableUser);
        if ($idUser!==null) $this->conn->where(['iduser'=>$idUser]);
        if ($user!==null) $this->conn->where(['user'=>$user]);
        if ($password!==null) $this->conn->where(['password'=>$password]);
        if ($email!==null) $this->conn->where(['email'=>$email]);
        try {
            $user = $this->conn->first();
        } catch(\Exception $e) {
            $this->conn->throwError($e->getMessage(),'from getUserFromDB '.$idUser);
        }
        if (empty($user)) {
            return false;
        } else {
            if (count($this->extraFields)) {
                foreach($this->extraFields as $key=>$value) {
                    $this->extraFields[$key]=@$user[$key];
                }
            }
            // load the groups (if any)
            if ($this->hasGroup) {
                try {
                    $userxGroup = $this->conn->select("r.name")
                        ->from($this->tableUserXGroup . " ur")
                        ->join($this->tableGroup . " r on ur.idgroup=r.idgroup")
                        ->where("ur.iduser=?", ['i', @$user['iduser']])
                        ->toList();
                } catch (\Exception $e) {
                    $this->conn->throwError($e->getMessage(),'from getUserFromDB '.$idUser);
                    return false;
                }
                $groups = [];
                foreach ($userxGroup as $tmp) {
                    $groups[] = $tmp['name'];
                }
            } else {
                $groups = [];
            }
            $this->factoryUser($user['user'],$user['password'],$user['fullname'],$groups,@$user['role'],$user['status'],$user['email'],$user['iduser'],$this->extraFields);
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
            $this->conn->throwError($e->getMessage(),"getActivateFromDB $idActivation");
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
            if ($this->hasGroup) $this->addUserxGroup($idUser,$idGroup);
            $this->conn->commit();
        } catch (\Exception $e) {
            if ($this->conn->transactionOpen) $this->conn->rollback();
            throw $e;
        }
        return $idUser;
    }

    /**
     * @param array['user'=>'name','password'=>'pwd','role'=>'AAA','fullname'=>'fullname','email'=>'email'] $user
     * @return mixed
     * @throws \Exception
     */
    private function addUserOnly($user) {
        $userDB=[];
        $userDB[$this->userMap['iduser']]=$user['iduser'];
        $userDB[$this->userMap['password']]=$this->encrypt($user['password']);
        $userDB[$this->userMap['user']]=$user['user'];
        $userDB[$this->userMap['email']]=$user['email'];
        $userDB[$this->userMap['fullname']]=$user['fullname'];
        $userDB[$this->userMap['status']]=$user['status'];
        if ($this->hasRole) $userDB[$this->userMap['role']]=$user['role'];
        if (count($this->extraFields)) {
            foreach($this->extraFields as $key=>$value) {
                $userDB[$key]=$user[$key];
            }
        }
        return $this->conn->set($userDB)
            ->from($this->tableUser)
            ->insert();
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
     * Logout and the session is destroyed. This redirect to the initial page.
     *
     * @param bool $redirect if true then it redirects and it stops the execution.
     */
    public function logout($redirect=true) {
        parent::logout();
        if ($redirect) {
            @header("location:" . $this->initPage);
            die(1);
        }
    }


	/**
	 * Validate if the user is logged.
	 * If it is not logged and it's not in the login page, then it's redirected to the login page
	 * If it is logged and it's in the login page, then it's redirected to the frontpage
	 * @param bool $redirect
	 */
    public function validate($redirect=true) {
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
            if ($redirect) $this->backLogin();
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
        return preg_match('#^/\w+#',$url)?$url:$default;
    }

    public function validateUser($us) {
        try {
            if ($this->messageList->errorcount==0) {
                $load = $this->getUserFromDB($us['user']);
                if ($load!==false) {
                    $this->messageList->addItem('user','User already exist');
                    $load=$this->getUserFromDB(null,null,null,$us['email']);
                    if ($load!==false) $this->messageList->addItem('email','Email already exist');
                }
            }
        } catch (\Exception $e) {
            $this->messageList->addItem('user','It\'s not possible to validate user');
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
     * Delete activation stored in the db by uid
     * @param $uid string
     * @throws \Exception
     */
    public function deleteActivation($uid) {
        $this->conn->delete($this->tableActivation,['idactivation','i',$uid]);
    }

    /**
     * @return BladeOne
     */
    public  function blade() {
		if($this->blade!=null) {
			return $this->blade;
		}
        if (function_exists("blade")) {

	        $this->blade=blade($this->templateRoot."/view",$this->templateRoot."/compile"); // we inject (if any)
        } else {
            $this->blade=new BladeOne($this->templateRoot."/view",$this->templateRoot."/compile",BladeOne::MODE_AUTO);
        }
        return $this->blade;
    }

	/**
	 * @param $template
	 * @param array $viewVariables Variables used for the view. For example, a list to fill a select.
	 * @throws \Exception
	 */
    public function registerCustomScreen($template, $viewVariables=[]) {
        $this->registerTemplate=$template;
        return $this->registerScreen("","","",$viewVariables);
    }

	/**
	 * @param string $title
	 * @param string $subtitle
	 * @param string $logo
	 * @param array $viewVariables
	 * @throws \Exception
	 */
    public function registerScreen($title="Register Screen",$subtitle="",$logo="https://avatars3.githubusercontent.com/u/19829219",$viewVariables=[]) {
        $blade=$this->blade();


        $button=$this->val->type('string')->post('button');
        $message="";
        $user=[];
        $success=false;
        if ($button==='register') {
            $user=[
                'button'=>$button
                ,'iduser'=>0
                ,'user'=>$this->val->type('string')
                    //->condition('betweenlen','The %field must have a length between %first and %second',[3,45])
                    ->post('user','Usuario incorrecto')
                ,'password'=>$this->val->type('string')
                    //->condition('betweenlen',"The %field must have a length between %first and %second",[3,64])
                    ->post('password')
                ,'password2'=>$this->val->type('string')
                    //->condition('betweenlen',"The %field must have a length between %first and %second",[3,64])
                    ->post('password2')
                ,'group'=>$this->defaultGroup
                ,'role'=>$this->defaultRole
                ,'fullname'=>$this->val->type('string')
                    //->condition('betweenlen',"The %field must have a length between %first and %second",[3,128])
                    ->post('fullname')
                ,'email'=>$this->val->type('string')
                 //   ->condition('minlen',null,3)
                //    ->condition('maxlen',null,45)
                ->post('email')];
            if (count($this->extraFields)) {
                foreach($this->extraFields as $key=>$value) {
                    $user[$key]=$this->val->type('string')
                        ->initial($value)
                        ->post($key);
                }
            }
            $this->val->type('string')->condition('eq','The passwords aren\'t equals',$user['password'])
                ->set($user['password2'],'password2');
            $this->validateUser($user);

            if ($this->messageList->errorcount) {
                $message=$this->messageList->firstErrorText();
                $success=false;
            } else {
                $success=$this->registerNewUser($user,$message,$title,$subtitle,$logo);
            }
        } else {
            if (count($this->extraFields)) {
                foreach($this->extraFields as $key=>$value) {
                    $user[$key]=$value; // default value.
                }
            }

        }
        if (!$success) {
            $returnUrl=$this->safeReturnUrl(@$_REQUEST['returnUrl'],$this->initPage);
            try {
                $fields=[
                    'button'=>$button
                    ,'iduser'=>0
                    ,'obj'=>$user
                    , 'returnUrl' => $returnUrl
                    , 'message' => $message
                    , 'error'=>$this->messageList
                    , 'title'=>$title
                    , 'subtitle'=>$subtitle
                    , 'logo'=>$logo];
                $fields=array_merge($fields,$viewVariables);
                echo $blade->run($this->registerTemplate, $fields);
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

	/**
	 * @param $user
	 * @param $message
	 * @param $title
	 * @param $subtitle
	 * @param $logo
	 * @return bool
	 * @throws \Exception
	 */
    private function registerNewUser($user,&$message,$title,$subtitle,$logo) {
        $blade=$this->blade();
        unset($user['password2']);
        if ($this->emailServer!==null) {
            $user['status'] = 1; // there is not an email, so the user is active by default
        } else {
            $user['status'] = 0; // not active until email activation.
        }
        try {
            $message='';
            $this->conn->startTransaction();
            $idUser = $this->addUser($user,$user['group']);
            $uid=$this->generateRandomString();
            // we send an activation by email
            if ($this->emailServer!==null) {
                $message="Unable to send email";
                $this->addActivation($uid,$idUser,1);
                $msg="It is the activation code $uid";
                $this->sendMail($user['email'], $user['fullname'], "Activation Code", $msg);
                $message="User created correctly. Please check your email to activate the account";
            } else {
                $message="User created and activated correctly";
            }
            $this->conn->commit(false);
            // register ok, email was send.
            echo $blade->run($this->registerOkTemplate, ['title' => $title
                , 'subtitle' => $subtitle
                , 'logo' => $logo
                , 'error'=>$this->messageList
                , 'message' => $message
                , 'email' => $user['email']]);
            die(1);
        } catch (\Exception $e) {
            if (!$this->debug) {
                $message="Unable to create user or activation ";
            } else {
                $message="Unable to create user or activation ".$e->getMessage();
            }
            $success=false;
            $this->conn->rollback(false);
        }
        return $success;
    }

	/**
	 * @param string $title
	 * @param string $subtitle
	 * @param string $icon
	 * @param string $iconemail
	 * @throws \Exception
	 */
    public function recoverScreen($title="Register Screen",$subtitle="",$icon="",$iconemail="") {

        $blade=$this->blade();

        $button=$this->val->type('string')->post('button');
        $message="";
        $user=[];
        switch ($button) {
            case 'user':
                $user=$this->val->type('string')->condition('maxlen',null,45)->post('user');
                
                if (!$user) {
                    $message="Debe ingresar un usuario";
                    $button=false;
                } else {
                    $us=false;
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
                    $us=false;
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
            $uid='';
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
                    $this->sendMail($this->email, $this->fullName, "Activation Code", $msg);
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
                    , 'error'=>$this->messageList
                    , 'subtitle'=>$subtitle
                    , 'logo'=>$icon]);
            } catch (\Exception $e) {
                echo "error showing register page";
            }
        }


    }

    public function activeScreen($title="Register Screen",$subtitle="",$iconOK="",$iconFail="") {

        $blade=$this->blade();

        $id=$this->val->type('integer')->def(0)->ifFailThenDefault()->get('id');
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
                try {
                    @$this->deleteActivation($id);
                } catch (\Exception $e2) {
                    $message="Unable to delete activation ".$e2->getMessage();
                }
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

        $blade=$this->blade();

        $id=$this->val->type('integer')->def(0)->ifFailThenDefault()->get('id');
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
            try {
                $r = $this->getUserFromDB(null, $idUser);
            } catch (\Exception $e) {
                $r=false;
            }
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
                , 'error'=>$this->messageList
                , 'user'=>$user
                , 'logo'=>$icon]);
        } catch (\Exception $e) {
            echo "error showing register page";
        }
    }
    public function logoutScreen() {
        if ($_COOKIE['phpcookiesess']) {
            $this->cookieID=$_COOKIE['phpcookiesess'];
            try {
                @$this->deleteCookie();
            } catch (\Exception $e) {
            }
        }
        $this->logout();
        session_write_close();
        @header("location:".$this->logoutPage);
        exit(1);
    }
    public function createTables() {
        $msgError=[];
        try {
            $sqlRole= ($this->hasRole)? "`{$this->userMap['role']}` VARCHAR(64) NOT NULL,":"";
            $sql= /** @lang text */
                "CREATE TABLE `{$this->tableUser}` (
            `{$this->userMap['iduser']}` INT NOT NULL,
            `{$this->userMap['user']}` VARCHAR(45) NOT NULL,
            `{$this->userMap['password']}` VARCHAR(64) NOT NULL,
            $sqlRole
            `{$this->userMap['status']}` int NOT NULL default 1,
            `{$this->userMap['fullname']}` VARCHAR(128) NOT NULL,
            `{$this->userMap['email']}` VARCHAR(45) NOT NULL,            
            PRIMARY KEY (`{$this->userMap['iduser']}`));
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
        if ($this->hasGroup) {
            try {
                $sql = /** @lang text */
                    "CREATE TABLE `{$this->tableGroup}` (
                `idgroup` INT NOT NULL,
                `name` VARCHAR(45) NOT NULL,
                PRIMARY KEY (`idgroup`));
                ALTER TABLE ``{$this->tableGroup}`` 
                ADD UNIQUE INDEX ``{$this->tableGroup}`_key1` (`name` ASC) VISIBLE;";
                $this->conn->runMultipleRawQuery($sql, true);
            } catch (\Exception $e) {
                $msgError[] = "Note: Table {$this->tableGroup} not created (maybe it exists) " . $e->getMessage() . "<br>";
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
        }

        try {
            $sql= /** @lang text */
                "CREATE TABLE `{$this->tableActivation}` (
            `idactivation` int NOT NULL,
            `iduser` int NOT NULL,
            `status` int NOT NULL,
            `date` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,           
            PRIMARY KEY (`idactivation`));";

            $this->conn->runMultipleRawQuery($sql, true);
        } catch (\Exception $e) {
            $msgError[]= "Note: Table {$this->tableActivation} not created (maybe it exists) ".$e->getMessage()."<br>\n$sql<br>";
        }
        return $msgError;
    }

    /**
     * If you want to use a custom login screen then it must have a :<br>
     * form(post)<br>
     * button(name=button)<br>
     * user(name=password)<br>
     * password(password)<br>
     * remember(name=rememeber,value=1) it is optional<br>
     * @param $template
     * @param array $viewVariables Variables used for the view. For example, a list to fill a select.
     */
    public function loginCustomScreen($template, $viewVariables=[]) {
        $this->loginTemplate=$template;
        return $this->loginScreen("","","",$viewVariables);
    }

    /**
     * @param string $title
     * @param string $subtitle
     * @param string $logo
     * @param array  $viewVariables
     */
    public function loginScreen($title="Login Screen",$subtitle="",$logo="https://avatars3.githubusercontent.com/u/19829219", $viewVariables=[]) {
        $blade=$this->blade();

        $button=$button=$this->val->type('string')->post('button');
        $message="";
        $user=$this->val->type('string')->condition('maxlen',null,45)->post('user');
        $password=$this->val->type('string')->condition('maxlen',null,64)->post('password');
        $remember=$this->val->type('string')->post('remember',"boolean");
        $logged=false;
        if ($button==='login') {

            $logged=$this->login($user
                ,$password
                ,$remember=='1');
            $message=(!$logged)?"User or password incorrect":"";
            if ($this->status == 0 && $logged) {
                $message = "User not active";
                $logged=false;
            }
        }
        if ($button!=='login' || !$logged) {
            $returnUrl=$this->safeReturnUrl(@$_REQUEST['returnUrl'],$this->initPage);
            try {
                $param=[
                    'button'=>$button
                    ,'user' => $user
                    , 'password' => $password
                    , 'remember'=> $remember
                    , 'returnUrl' => $returnUrl
                    , 'message' => $message
                    , 'title'=>$title
                    , 'subtitle'=>$subtitle
                    , 'logo'=>$logo
                    , 'useCookie'=>$this->useCookie];
                $param=array_merge($param,$viewVariables);
                echo $blade->run($this->loginTemplate,$param );
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