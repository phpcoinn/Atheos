<?php
//PHPCoin - SC sidebar

require_once("common.php");


error_reporting(E_ALL^E_NOTICE);
require_once '/var/www/phpcoin/include/init.inc.php';

if(isset($_POST['ajax'])) {
    $ajax = true;
    $formData = $_POST['formData'];
    unset($_SESSION['msg']);
    parse_str($formData, $_POST);
    ob_end_clean();
    ob_start();
    register_shutdown_function(function () {
        $content = ob_get_contents();
        echo $content;
    });
}

$engines = [
	"virtual" => [
		"name" => "Virtual",
        "NODE_URL" => "https://node1.phpcoin.net",
	],
//	"virtual" => [
//		"name" => "Virtual",
//        "NODE_URL" => "http://spectre:8000",
//	],
//	"local" => [
//		"name" => "Local",
//		"NODE_URL" => "http://spectre:8000",
//        "atheos_url"=>"http://atheos.spectre:8000/"
//	],
//	"testnet" => [
//		"name" => "Testnet",
//		"NODE_URL" => "https://node1.phpcoin.net",
//	],
//	"testnet" => [
//		"name"=>"Testnet"
//    ],
//	"mainnet" => [
//		"name"=>"Mainnet"
//	],
];



//require_once ROOT . '/include/class/SmartContractEngine.php';
//require_once ROOT . '/include/class/SmartContract.php';
//require_once ROOT . '/include/class/Transaction.php';
//require_once ROOT . '/include/class/Account.php';
//require_once ROOT . '/include/functions.inc.php';
//require_once ROOT . '/include/dapps.functions.php';

function api_get($url) {
	$res = @file_get_contents(NODE_URL . $url);
	$res = json_decode($res, true);
	$status = $res['status'];
	if($status == "ok") {
		$data = $res['data'];
		return $data;
	}
}

function api_post($url, $data = [], $timeout = 60, $debug = false) {
	$url = NODE_URL . $url;
	$postdata = http_build_query(
		[
			'data' => json_encode($data),
			"coin" => COIN,
			"network"=> NETWORK,
		]
	);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS,$postdata );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, DEVELOPMENT ? 0 : 2);
	curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, !DEVELOPMENT);
	$result = curl_exec($ch);
	curl_close ($ch);
	$res = json_decode($result, true);
	return $res;
}

if(isset($_SESSION['engine'])) {
    $engine = $_SESSION['engine'];
} else {
    $engine = "virtual";
}

//$engine = "testnet-local";

$virtual = $engine == "virtual";


define("NODE_URL", @$engines[$engine]['NODE_URL']);

if(isset($_GET['auth_data'])) {
	$auth_data = json_decode(base64_decode($_GET['auth_data']), true);
	if($_SESSION['auth_request_code'] == $auth_data['request_code']) {
		$_SESSION['account']=$auth_data['account'];
	}
	header("location: ".$engines[$_SESSION['engine']]['atheos_url']);
	exit;
}

if(isset($_GET['signature_data'])) {
    $signature_data = json_decode(base64_decode($_GET['signature_data']), true);
    if(isset($signature_data['signature'])) {
        $_SESSION['contract']['signature']=$signature_data['signature'];
        $_SESSION['contract']['status']="signed";
    }
    header("location: ".$engines[$_SESSION['engine']]['atheos_url']."?deploy=1");
    exit;
}

if(isset($_GET['response'])) {


}

function resetForm() {
    $_SESSION['account']=null;
    $_SESSION['accounts']=[];
    $_SESSION['contract']=null;
    $_SESSION['sc_account']=null;
    $_SESSION['transactions'] = [];
    $_SESSION['property_key'] = null;
    $_SESSION['get_property_val'] = null;
    $_SESSION['view_method_val'] = null;
    $_SESSION['engine'] = 'virtual';
    $_SESSION['deploy_amount'] = null;
    $_SESSION['deploy_params'] = null;
    header("location: ". $_SERVER['REQUEST_URI']);
    exit;
}

function compile() {
    $phar_file = ROOT . "/tmp/sc/".uniqid().".phar";

    $source = $_POST['source'];
    $folder = getProjectFolder();
    if($source == "folder") {
        $file =$folder."/index.php";
    } else {
        $file = substr($source, 5);
        $file = $folder."/".$file;
    }
    $_SESSION['source']=$source;
    unset($_SESSION['contract']);

    $_SESSION['contract']['phar_code']=null;
    $res = SmartContract::compile($file, $phar_file, $err);
    if(!$res) {
        $_SESSION['msg']=[['icon'=>'error', 'text'=>'Error compiling smart contract:'.$err]];
        header("location: ".$_SERVER['REQUEST_URI']);
        exit;
    }
    $code=base64_encode(file_get_contents($phar_file));
    $interface = SmartContractEngine::verifyCode($code, $error);
    if(!$interface) {
        $_SESSION['msg']=[['icon'=>'error', 'text'=>'Error verify smart contract:'.$error]];
        header("location: ".$_SERVER['REQUEST_URI']);
        exit;
    }
    $_SESSION['contract']['phar_code']=$code;
    $_SESSION['contract']['interface']=$interface;
    $_SESSION['contract']['status']="compiled";
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

function sign_contract($virtual) {
    global $engines;
    $request_code = uniqid();
    $_SESSION['request_code']=$request_code;
    $redirect = urlencode( $engines[$_SESSION['engine']]['atheos_url']."?");

    $address=$_SESSION['account']['address'];
    $amount=$_POST['deploy_amount'];

    $_SESSION['contract']['deploy_amount']=$_POST['deploy_amount'];
    $_SESSION['contract']['deploy_params']=$_POST['deploy_params'];

    if(empty($amount)) $amount=0;

    $post_deploy_params = $_POST['deploy_params'];
    $arr=explode(",",$post_deploy_params);
    $deploy_params = [];
    foreach ($arr as $item) {
        $item = trim($item);
        if(strlen($item) == 0) continue;
        $deploy_params[]=$item;
    }

    $data = [
        "code" => $_POST['compiled_code'],
        "amount" => num($amount),
        "params"=>$deploy_params
    ];

    $text = base64_encode(json_encode($data));

    if($virtual) {
        $_SESSION['contract']['signature']=ec_sign($text, $_SESSION['account']['private_key']);
        header("location: ".$_SERVER['REQUEST_URI']);
        exit;
    }

    $url=NODE_URL . "/dapps.php?url=".MAIN_DAPPS_ID."/gateway/sign.php?&app=Atheos&address=$address&redirect=$redirect&no_return_message=1";
    echo '<!doctype html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport"
                  content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
            <meta http-equiv="X-UA-Compatible" content="ie=edge">
            <title>Document</title>
        </head>
        <body>
            <form id="post_form" action="'.$url.'" method="post">
                <input type="hidden" name="message" value="'.htmlentities($text).'"/>
            </form>
            <script type="text/javascript">
                document.getElementById(\'post_form\').submit();
            </script>
        </body>
        </html>';

    exit;
}

function deploy($virtual) {
    global $engines;
    $address = $_SESSION['account']['address'];
    if(empty($address)) {
        $_SESSION['msg']=[['icon'=>'error', 'text'=>'Wallet address is required']];
        header("location: ".$_SERVER['REQUEST_URI']);
        exit;
    }
    $deploy_address = $_POST['deploy_address'];
    if(empty($deploy_address)) {
        $_SESSION['msg']=[['icon'=>'error', 'text'=>'Contract address is required']];
        header("location: ".$_SERVER['REQUEST_URI']);
        exit;
    }

    if($virtual && $deploy_address == $address) {
        $_SESSION['msg']=[['icon'=>'error', 'text'=>'Invalid contract address']];
        header("location: ".$_SERVER['REQUEST_URI']);
        exit;
    }

    if(!Account::valid($deploy_address)) {
        $_SESSION['msg']=[['icon'=>'error', 'text'=>'Contract address is not valid']];
        header("location: ".$_SERVER['REQUEST_URI']);
        exit;
    }

    $_SESSION['deploy_address']=$deploy_address;

    $smartContract = api_get("/api.php?q=getSmartContract&address=$deploy_address");
    if($smartContract && !$virtual) {
        $_SESSION['msg']=[['icon'=>'error', 'text'=>'Smart contract with address already exists']];
        header("location: ".$_SERVER['REQUEST_URI']);
        exit;
    }

    $public_key = $_SESSION['account']['public_key'];

    $date = $_POST['date'];
    $compiled_code = $_POST['compiled_code'];
    $amount=$_POST['deploy_amount'];
    if(empty($amount)) $amount=0;

    $_SESSION['contract']['deploy_amount']=$amount;

    $name=$_POST['contract_name'];
    $description=$_POST['contract_description'];


    $_SESSION['deploy_params']=$_POST['deploy_params'];
    $post_deploy_params = $_POST['deploy_params'];
    $arr=explode(",",$post_deploy_params);
    $deploy_params = [];
    foreach ($arr as $item) {
        $item = trim($item);
        if(strlen($item) == 0) continue;
        $deploy_params[]=$item;
    }

    $interface = SmartContractEngine::verifyCode($compiled_code, $error);

    $data = [
        "code"=>$compiled_code,
        "amount"=>num($amount),
        "params"=>$deploy_params,
        "name"=>$name,
        "description"=>$description,
        "interface"=>$interface
    ];

    $_SESSION['deploy_amount']=$_POST['deploy_amount'];
    $_SESSION['contract']['deploy_params']=$_POST['deploy_params'];
    $_SESSION['contract']['address']=$deploy_address;
    $_SESSION['contract']['name']=$name;
    $_SESSION['contract']['description']=$description;
    $_SESSION['contract']['interface']=$interface;

    unset($_SESSION['get_property_val']);
    unset($_SESSION['interface_tab']);
    unset($_SESSION['method_type']);
    unset($_SESSION['params']);
    unset($_SESSION['amount']);

    $txdata = base64_encode(json_encode($data));

    if($virtual) {
        $signature = ec_sign($txdata, $_SESSION['account']['private_key']);
    } else if (empty($_SESSION['contract']['signature'])) {
        file_put_contents("/home/marko/web/phpcoin/node/tmp/txdata", json_encode($data));
        $redirect = urlencode($engines[$_SESSION['engine']]['atheos_url']."?");
        $url=NODE_URL . "/dapps.php?url=".MAIN_DAPPS_ID."/gateway/sign.php?&app=Atheos&address=$address&redirect=$redirect&no_return_message=1";
        echo '<!doctype html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport"
                  content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
            <meta http-equiv="X-UA-Compatible" content="ie=edge">
            <title>Document</title>
        </head>
        <body>
            <form id="post_form" action="'.$url.'" method="post">
                <input type="hidden" name="message" value="'.htmlentities($txdata).'"/>
            </form>
            <script type="text/javascript">
                document.getElementById(\'post_form\').submit();
            </script>
        </body>
        </html>';

        exit;
    } else {
        $signature=$_SESSION['contract']['signature'];
    }


    $msg = $signature;
    $_SESSION['contract']['code']=$txdata;
    $deploy_amount = floatval($_POST['deploy_amount']);

    $transaction = new Transaction($public_key,$deploy_address,$deploy_amount,TX_TYPE_SC_CREATE,$date, $msg, TX_SC_CREATE_FEE);
    $transaction->data = $txdata;

    if($virtual) {
        $hash = $transaction->hash();

        SmartContractEngine::$virtual = true;
        SmartContractEngine::$smartContract = $_SESSION['contract'];
        $res = SmartContractEngine::deploy($transaction, 0, $err);

        if(!$res) {
            $_SESSION['msg']=[['icon'=>'error', 'text'=>'Smart contract not deployed: '.$err]];
            header("location: ".$_SERVER['REQUEST_URI']);
            exit;
        } else {
            $_SESSION['transactions'][]=$transaction->toArray();
            $_SESSION['accounts'][$address]['balance']=$_SESSION['accounts'][$address]['balance'] - TX_SC_CREATE_FEE;
            $_SESSION['accounts'][$deploy_address]['balance']+=$deploy_amount;
        }

        $_SESSION['contract']['address']=$deploy_address;
        $_SESSION['contract']['height']=1;
        $_SESSION['contract']['code']=$txdata;
        $_SESSION['contract']['signature']=$signature;
        $_SESSION['contract']['status']="deployed";

        header("location: ".$_SERVER['REQUEST_URI']);
        exit;

    } else {

        $request_code = uniqid();
        $_SESSION['auth_request_code']=$request_code;
        $redirect = urlencode($engines[$_SESSION['engine']]['atheos_url']."?");
        $tx = [
            "src"=>$transaction->src,
            "dst"=>$transaction->dst,
            "val"=>$transaction->val,
            "msg"=>$transaction->msg,
            "fee"=>$transaction->fee,
            "type" => $transaction->type,
            "date"=>$transaction->date
        ];
        $_SESSION['tx']=$tx;
        $tx = base64_encode(json_encode($tx));
        $request_code = uniqid("signtx");
        $_SESSION['request_code']=$request_code;

        $url=NODE_URL . "/dapps.php?url=".MAIN_DAPPS_ID."/gateway/approve.php?&app=Atheos&request_code=$request_code&&redirect=$redirect&tx=$tx";

        echo '<!doctype html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport"
                  content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
            <meta http-equiv="X-UA-Compatible" content="ie=edge">
            <title>Document</title>
        </head>
        <body>
            <form id="post_form" action="'.$url.'" method="post">
                <input type="hidden" name="txdata" value="'.htmlentities($transaction->data).'"/>
            </form>
            <script type="text/javascript">
                document.getElementById(\'post_form\').submit();
            </script>
        </body>
        </html>';


        exit;
    }
}

function save() {
    $engine = $_POST['engine'];
    $_SESSION['engine'] = $engine;
    $virtual = $engine == "virtual";
    if(!$virtual) {
        unset($_SESSION['account']);
//        unset($_SESSION['sc_account']);
//	    $_SESSION['account']['address'] = $_POST['address'];
//	    $_SESSION['account']['private_key'] = $_POST['private_key'];
//	    $_SESSION['sc_account']['address'] = $_POST['sc_address'];
    }
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

function set_contract() {
    $contract = $_POST['contract'];
    $address = $_SESSION['account']['address'];
    $deployedContracts = api_get("/api.php?q=getDeployedSmartContracts&address=$address");
    $_SESSION['contract']=null;
    foreach ($deployedContracts as $deployedContract) {
        if($deployedContract['address']==$contract) {
            $_SESSION['contract']=$deployedContract;
            $_SESSION['contract']['interface'] = SmartContractEngine::getInterface($_SESSION['contract']['address']);
            break;
        }
    }
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

function get_source() {
    $code = $_SESSION['contract']['phar_code'];
    $code=base64_decode($code);
    if(isset($_SESSION['contract']['address'])) {
        $name=$_SESSION['contract']['address'];
    } else {
        $name = md5($code);
    }
    $phar_file = ROOT . "/tmp/".$name.".phar";
    file_put_contents($phar_file, $code);

    ob_end_clean();
    header("Content-Description: File Transfer");
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"". basename($phar_file) ."\"");

    readfile ($phar_file);
    exit();
}

function exec_method($virtual) {
    global $engines;
    $address = $_POST['address'];
    $fn_address = $_POST['fn_address'];
    $amount = $_POST['amount'];
    $method_type=$_POST['method_type'];

    $_SESSION['fn_address']=$fn_address;
    $_SESSION['amount']=$amount;
    $_SESSION['method_type']=$method_type;
    $_SESSION['interface_tab']="methods";

    if(strlen($amount)==0) $amount=0;

    if(empty($method_type)) {
        $_SESSION['msg']=[['icon'=>'error', 'text'=>'Method type not selected']];
        header("location: ".$_SERVER['REQUEST_URI']);
        exit;
    }

    if($method_type == "exec") {
        if(empty($fn_address)) {
            $fn_address=$_SESSION['contract']['address'];
        }
        $type=TX_TYPE_SC_EXEC;
        $public_key = $_SESSION['account']['public_key'];
        $src=$address;
        $dst=$fn_address;
    } else if ($method_type == "send") {
        if(!$virtual) {
            $smartContract = api_get("/api.php?q=getSmartContract&address=".$_SESSION['account']['address']);
            if(!$smartContract) {
                $_SESSION['msg']=[['icon'=>'error', 'text'=>'Connect smart contract wallet for call send method']];
                header("location: ".$_SERVER['REQUEST_URI']);
                exit;
            }
        }
        $type=TX_TYPE_SC_SEND;
        $public_key = $_SESSION['account']['public_key'];
        $src=$_SESSION['account']['address'];
        $dst=$fn_address;
        if(empty($dst)) {
            $_SESSION['msg']=[['icon'=>'error', 'text'=>'Destination address must be specified']];
            header("location: ".$_SERVER['REQUEST_URI']);
            exit;
        }
        if($virtual && $dst == $src) {
            $_SESSION['msg']=[['icon'=>'error', 'text'=>'Invalid destination address']];
            header("location: ".$_SERVER['REQUEST_URI']);
            exit;
        }
    }

    $exec_method = array_keys($_POST['exec_method'])[0];
    $_SESSION['params'][$exec_method]=$_POST['params'][$exec_method];

    $params = [];
    if(isset($_POST['params'][$exec_method])) {
        $post_params = $_POST['params'][$exec_method];
        $post_params = explode(",", $post_params);
        foreach($post_params as &$item) {
            $item = trim($item);
            if(strlen($item)>0) {
                $params[]=$item;
            }
        }
    }

    $data = [
        "method" => $exec_method,
        "params" => $params
    ];

    $msg=base64_encode(json_encode($data));

    if($virtual) {
        $date = $_POST['date'];
        $transaction = new Transaction($public_key,$dst,$amount,$type,$date, $msg, TX_SC_EXEC_FEE);
        $hash = $transaction->hash();

        SmartContractEngine::$virtual = true;
        SmartContractEngine::$smartContract = $_SESSION['contract'];

        $res = SmartContractEngine::process($_SESSION['contract']['address'], [$transaction], count($_SESSION['transactions']), false, $err);
        if(!$res) {
            $_SESSION['msg']=[['icon'=>'error', 'text'=>'Method can not be executed: '.$err]];
            header("location: ".$_SERVER['REQUEST_URI']);
            exit;
        }
        $_SESSION['accounts'][$src]['balance'] = $_SESSION['accounts'][$src]['balance'] - (TX_SC_EXEC_FEE + $amount);
        $_SESSION['accounts'][$dst]['balance'] = $_SESSION['accounts'][$dst]['balance'] + $amount;

        $_SESSION['transactions'][]=$transaction->toArray();

    } else {

        $request_code = uniqid();
        $_SESSION['auth_request_code']=$request_code;
        $redirect = urlencode($engines[$_SESSION['engine']]['atheos_url']."?");
        $tx = [
            "src"=>$src,
            "dst"=>$dst,
            "val"=>$amount,
            "msg"=>$msg,
            "fee"=>TX_SC_EXEC_FEE,
            "type" => $type,
            "date"=>time()
        ];
        $_SESSION['tx']=$tx;
        $tx = base64_encode(json_encode($tx));
        $request_code = uniqid("signtx");
        $_SESSION['request_code']=$request_code;

        $url=NODE_URL . "/dapps.php?url=".MAIN_DAPPS_ID."/gateway/approve.php?&app=Faucet&request_code=$request_code&tx=$tx&redirect=$redirect";
        header("location: $url");
        exit;
    }

    $_SESSION['interface_tab']="methods";
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

function view_method($virtual) {
    $account = $_POST['address'];
    $sc_address = $_SESSION['contract']['address'];
    $view_method = array_keys($_POST['view_method'])[0];

    $_SESSION['interface_tab']="views";

    $params = [];
    if(isset($_POST['params'][$view_method])) {
        $view_method_params = explode(",", $_POST['params'][$view_method]);
        foreach($view_method_params as &$item) {
            $item = trim($item);
            if(strlen($item)>0) {
                $params[]=$item;
            }
        }
    }
    if($virtual) {
        SmartContractEngine::$virtual = true;
        SmartContractEngine::$smartContract = $_SESSION['contract'];
        $res = SmartContractEngine::view($sc_address, $view_method, $params, $err);
    } else {
        $params = base64_encode(json_encode($params));
        $sc_address = $_SESSION['contract']['address'];
        $res = api_get("/api.php?q=getSmartContractView&address=$sc_address&method=$view_method&params=$params");
    }
    if($err) {
        $_SESSION['msg']=[['icon'=>'error', 'text'=>'View can not be executed: '.$err]];
        header("location: ".$_SERVER['REQUEST_URI']);
        exit;
    }
    $_SESSION['view_method_val'][$view_method]=$res;
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

function get_property_val($virtual) {
    $property = array_keys($_POST['get_property_val'])[0];
    $sc_address = $_SESSION['contract']['address'];
    $mapkey = null;
    if(isset($_POST['property_key'][$property])) {
        $mapkey = $_POST['property_key'][$property];
        $_SESSION['property_key'][$property]=$mapkey;
    }
    if($virtual) {
        SmartContractEngine::$virtual = true;
        SmartContractEngine::$smartContract = $_SESSION['contract'];
        $val = SmartContractEngine::get($sc_address, $property, $mapkey);
    } else {
        $val = api_get("/api.php?q=getSmartContractProperty&address=$sc_address&property=$property&key=$mapkey".XDEBUG);
    }
    $_SESSION['get_property_val'][$property]=$val;
    $_SESSION['interface_tab']="properties";
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

function clear_property_val() {
    $property = array_keys($_POST['clear_property_val'])[0];
    unset($_SESSION['get_property_val'][$property]);
    unset($_SESSION['property_key'][$property]);
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

function clear_view_method_val() {
    $property = array_keys($_POST['clear_view_method_val'])[0];
    unset($_SESSION['view_method_val'][$property]);
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

function wallet_auth() {
    global $engines;
    $request_code = uniqid();
    $_SESSION['auth_request_code']=$request_code;
    $redirect = urlencode($engines[$_SESSION['engine']]['atheos_url']."?");
    $url=NODE_URL."/dapps.php?url=".MAIN_DAPPS_ID."/wallet/auth.php?app=Athoes&request_code=$request_code&redirect=$redirect";
    header("location: $url");
    exit;
}

function logout() {
    unset($_SESSION['account']);
    unset($_SESSION['accounts']);
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

function download($virtual) {

    $address=$_SESSION['contract']['address'];
    if($virtual) {
        $code=$_SESSION['contract']['code'];
    } else {
        $smartContract = api_get("/api.php?q=getSmartContract&address=".$address);
        $code=$smartContract['code'];
    }
    $decoded=json_decode(base64_decode($code), true);
    $code=base64_decode($decoded['code']);

    $phar_file = ROOT . "/tmp/".$address.".phar";
    file_put_contents($phar_file, $code);

    ob_end_clean();
    header("Content-Description: File Transfer");
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"". basename($phar_file) ."\"");

    readfile ($phar_file);
    exit();
}

function getProjectFolder() {
    $folder = $_SESSION['projectPath'];
    if(strpos($folder, WORKSPACE) === false) {
        $folder = WORKSPACE . "/" . $folder;
    }
    return $folder;
}
if(isset($_GET['get_source'])) get_source();

if(isset($_POST['reset'])) resetForm();
if(isset($_POST['compile'])) compile();
//if(isset($_POST['sign_contract'])) sign_contract($virtual);
if(isset($_POST['deploy'])) deploy($virtual);
if(isset($_POST['save'])) save();
if(isset($_POST['set_contract'])) set_contract();
if(isset($_POST['exec_method'])) exec_method($virtual);
if(isset($_POST['view_method'])) view_method($virtual);
if(isset($_POST['get_property_val'])) get_property_val($virtual);
if(isset($_POST['clear_property_val'])) clear_property_val();
if(isset($_POST['clear_view_method_val'])) clear_view_method_val();
if(isset($_POST['wallet_auth'])) wallet_auth();
if(isset($_POST['logout'])) logout();
if(isset($_POST['download'])) download($virtual);

if(isset($_POST['method_type'])) {
    $_SESSION['method_type']=$_POST['method_type'];
}

if(isset($_POST['address']) || isset($_POST['sc_address'])) {
	foreach ($_SESSION['accounts'] as $account) {
		if($_POST['address'] == $account['address']) {
			$_SESSION['account'] = $account;
			break;
		}
	}

	foreach ($_SESSION['accounts'] as $account) {
		if($_POST['sc_address'] == $account['address']) {
			$_SESSION['sc_account'] = $account;
			break;
		}
	}
	header("location: ".$_SERVER['REQUEST_URI']);
	exit;
}




#### view

if($virtual) {
    if(!isset($_SESSION['account'])) {
	    $_SESSION['accounts']= [];
        for($i=1;$i<=5;$i++) {
            $account = api_get( "/api.php?q=generateAccount");
	        $account['balance'] =1000;
	        $_SESSION['accounts'][$account['address']]=$account;
        }
	    $_SESSION['account']=$_SESSION['accounts'][array_keys($_SESSION['accounts'])[0]];
    }
    if(!isset($_SESSION['sc_account'])) {
	    $_SESSION['sc_account'] = api_get( "/api.php?q=generateAccount");
    }
}


$address = "";
$balance = 0;
$deployedContracts=[];
if(isset($_SESSION['account'])) {
	$address = $_SESSION['account']['address'];
	$balance = $virtual ? $_SESSION['accounts'][$address]['balance'] ?? 0 : api_get("/api.php?q=getBalance&address=$address");
	$public_key = $virtual ? $_SESSION['account']['public_key'] : api_get("/api.php?q=getPublicKey&address=$address");
	$private_key = $virtual ? $_SESSION['account']['private_key'] : null;
    if(!$virtual) {
        $deployedContracts = api_get("/api.php?q=getDeployedSmartContracts&address=$address");
        $smartContract = api_get("/api.php?q=getSmartContract&address=".$_SESSION['contract']['address']);
        if($smartContract) {
            $_SESSION['contract']=$smartContract;
            $_SESSION['contract']['status']="deployed";
        } else {
            $txs = api_get("/api.php?q=getTransactions&address=".$_SESSION['contract']['address']);
            if(is_array($txs)){
                foreach ($txs as $tx) {
                    if($tx['type_label']=="mempool" && $tx['type_value']==TX_TYPE_SC_CREATE) {
                        $_SESSION['contract']['status']="in mempool";
                    }
                }
            }
        }
    }
}


$smartContract = false;
$sc_address = null;
$compiled_code = null;
$sc_balance = 0;
if(isset($_SESSION['contract'])) {
	$sc_address = $_SESSION['contract']['address'];
	$sc_balance = $virtual ? $_SESSION['accounts'][$sc_address]['balance'] ?? 0 : api_get( "/api.php?q=getBalance&address=$sc_address");
    $_SESSION['contract']['balance']=$sc_balance;
}

if(isset($_SESSION['contract'])) {
    $smartContract = $_SESSION['contract'];
    $code = $smartContract['code'];
    $compiled_code = $code;
    $interface = false;
    if($virtual) {
        SmartContractEngine::$virtual = true;
        SmartContractEngine::$smartContract = @$_SESSION['contract'];
        if(isset($_SESSION['contract']['interface'])) {
            $interface = $_SESSION['contract']['interface'];
        }
    } else {
        $_SESSION['contract']['interface'] = api_get("/api.php?q=getSmartContractInterface&address=".$_SESSION['contract']['address'].XDEBUG);
    }
}



$transactions = [];
if($virtual) {
	if (isset($_SESSION['transactions'])) {
		$transactions = $_SESSION['transactions'];
	}
} else {
	$transactions = api_get("/api.php?q=getTransactions&address=$address&limit=10");
    if(empty($transactions)) {
	    $transactions = [];
    }
}

$folder = getProjectFolder();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS));
$list = array();
foreach ($rii as $file) {
    if ($file->isDir()){
        continue;
    }
    $list[] = $file->getPathname();
}
$files = [];
foreach($list as $item) {
    if(strpos($item, $folder) !== false) {
	    $files[]=str_replace($folder, "", $item);
    }
}

asort($files);

$activeUser = SESSION("user");
$settings = @$_SESSION['settings'];
?>

<form id="phpcoin-sb" class="p-2" method="post" action="" onsubmit="onSubmit(event)">

    <div class="flex flex-wrap align-items-center align-content-between">
        <div class="">
            <h4>Smart Contract actions</h4>
        </div>
        <div class="ml-auto">
            <button name="save" type="submit" class="p-1">Save</button>
            <button name="reset" type="submit" class="p-1">Reset</button>
        </div>
    </div>
    <hr/>
    <div class="grid align-items-center grid-nogutter">
        <div class="col-12 sm:col-3">Engine:</div>
        <div class="col-12 sm:col-9">
            <select name="engine" class="p-1">
                <?php foreach ($engines as $engine_id => $engine_config) { ?>
                    <option value="<?php echo $engine_id ?>" <?php if($engine_id == $engine) { ?>selected="selected"<?php } ?>><?php echo $engine_config['name'] ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

	<?php if ($virtual) { ?>
        <div class="grid align-items-center grid-nogutter">
            <div class="col-12 sm:col-3">
                Wallet address:
            </div>
            <div class="col-12 sm:col-9">
                <select name="address" onchange="this.form.submit()">
                    <?php foreach($_SESSION['accounts'] as $account) { ?>
                        <option value="<?php echo $account['address'] ?>"
                                <?php if ($_SESSION['account']['address']==$account['address']) { ?>selected="selected"<?php } ?>>
                            <?php echo $account['address'] ?>
                            <?php if ($account['address'] == @$_SESSION['contract']['address']) { ?> (SmartContract)<?php } ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-12 sm:col-3">
                Private key:
            </div>
            <div class="col-12 sm:col-9">
                <input type="text" value="<?php echo $private_key ?>" class="p-1" name="private_key" <?php if($virtual) { ?>readonly="readonly"<?php } ?>/>
            </div>
            <div class="col-12 sm:col-3">
                Balance:
            </div>
            <div class="col-12 sm:col-9 flex flex-wrap">
                <div><?php echo num($balance) ?></div>
                <div>(<?php echo $_SESSION['account']['address'] ?>)</div>
            </div>
        </div>
    <?php } else { ?>
        <div class="grid align-items-center grid-nogutter">
            <div class="col-12 sm:col-3">
                Wallet:
	            <?php if (isset($_SESSION['account'])) { ?>
                    <br/>
                    <button name="logout" type="submit" class="p-1">Logout</button>
                <?php } ?>
            </div>
            <div class="col-12 sm:col-9">
                <?php if (isset($_SESSION['account'])) { ?>
                    <a target="_blank" href="<?php echo NODE_URL ?>/apps/explorer/address.php?address=<?php echo $_SESSION['account']['address'] ?>">
                        <?php echo $_SESSION['account']['address'] ?>
                    </a>
                        <?php if (!empty($_SESSION['account']['account_name'])) { ?>
                            (<?php echo $_SESSION['account']['account_name'] ?>)
                        <?php } ?>
                    <input type="hidden" name="address" value="<?php echo $_SESSION['account']['address'] ?>"/>
                    <br/>
                    (<?php echo $balance ?>)
                <?php } else { ?>
                    <button name="wallet_auth" type="submit" class="p-1">Authenticate</button>
                <?php } ?>
            </div>
        </div>
    <?php } ?>


    <hr/>
    <?php if (count($deployedContracts)>0) { ?>
        Deployed contracts:
        <div class="grid align-items-center grid-nogutter">
            <div class="col-12 sm:col-3">Contract:</div>
            <div class="col-12 sm:col-9">
                <div class="flex">
                    <select name="contract" class="p-1 w-auto">
                        <option value="">Select...</option>
                        <?php foreach($deployedContracts as $contract) { ?>
                            <option value="<?php echo $contract['address'] ?>" <?php if ($contract['address']==@$_SESSION['contract']['address']) { ?>selected<?php } ?>>
                                <?php echo $contract['address'] ?>
                                <?php if (!empty($contract['name'])) echo " (" . $contract['name'] .")" ?>
                            </option>
                        <?php } ?>
                    </select>
                    <button name="set_contract" class="p-1">Set contract</button>
                    <button name="login_contract" class="p-1">Login</button>
                </div>
            </div>
            <?php if ($_SESSION['contract']['status']=="deployed") { ?>
                <div class="col-12 sm:col-3">Height:</div>
                <div class="col-12 sm:col-9"><?php echo $_SESSION['contract']['height'] ?></div>
                <div class="col-12 sm:col-3">
                    Code:<br/>
                    <button type="submit" name="download" class="p-1">Download</button>
                </div>
                <div class="col-12 sm:col-9">
                    <textarea readonly><?php echo $_SESSION['contract']['code'] ?></textarea>
                </div>
                <div class="col-12 sm:col-3">Signature:</div>
                <div class="col-12 sm:col-9">
                    <input type="text" value="<?php echo $_SESSION['contract']['signature'] ?>" readonly/>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if(@$_SESSION['contract']['status']!="deployed" || true) { ?>
        <hr/>
        Contract source
        <div class="grid align-items-center grid-nogutter">
            <div class="col-12 sm:col-3">Source:</div>
            <div class="col-12 sm:col-9">
                <div class="flex flex-wrap">
                    <select name="source" class="p-1" <?php if (@$_SESSION['contract']['status']=="deployed" && !$virtual) { ?>disabled<?php } ?>>
                        <option value="folder" <?php if(@$_SESSION['source']=="folder") { ?>selected="selected"<?php } ?>>Whole folder</option>
                        <?php foreach($files as $file) { ?>
                            <option value="file:<?php echo $file ?>" <?php if(@$_SESSION['source']=="file:$file") { ?>selected="selected"<?php } ?>><?php echo $file ?></option>
                        <?php } ?>
                    </select>
                    <button type="submit" name="compile" class="p-1" <?php if (@$_SESSION['contract']['status']=="deployed" && !$virtual) { ?>disabled<?php } ?>>Compile</button>
                </div>
            </div>
        </div>
        <?php if (!empty($_SESSION['contract']['phar_code'])) { ?>
            <div class="grid align-items-center grid-nogutter">
                <div class="col-12 sm:col-3">
                    Compiled code:
                    <br/>
                    <a href="/phpcoin-sb.php?get_source" target="_blank">
                        <button type="button">Source</button>
                    </a>
                </div>
                <div class="col-12 sm:col-9">
                    <textarea name="compiled_code" rows="3" cols="30" readonly="readonly" <?php if (@$_SESSION['contract']['status']=="deployed") { ?>disabled<?php } ?>><?php echo $_SESSION['contract']['phar_code'] ?></textarea>
                </div>
            </div>
        <?php } ?>

        <?php if (!empty($_SESSION['contract']['phar_code'])) {
            $disabled = $_SESSION['contract']['status']=="in mempool" || $_SESSION['contract']['status']=="deployed";
            $deploy_params=[];
            foreach ($_SESSION['contract']['interface']['deploy']['params'] as $param) {
                $deploy_params[]=$param['name'];
            }
            ?>
            <hr/>
            <h4><strong>Deploy contract (<?php echo @$_SESSION['contract']['status'] ?>)</strong></h4>
            <div class="grid align-items-center grid-nogutter">
                <div class="col-12 sm:col-3">Name</div>
                <div class="col-12 sm:col-9">
                    <input type="text" name="contract_name" value="<?php echo $_SESSION['contract']['name'] ?>" placeholder="Name"
                           <?php if($disabled) { ?>readonly<?php } ?>/>
                </div>
                <div class="col-12 sm:col-3">Description</div>
                <div class="col-12 sm:col-9">
                    <input type="text" name="contract_description" value="<?php echo $_SESSION['contract']['description'] ?>" placeholder="Description"
                           <?php if($disabled) { ?>readonly<?php } ?>/>
                </div>
                <div class="col-12 sm:col-3">Amount</div>
                <div class="col-12 sm:col-9">
                    <input type="text" name="deploy_amount" value="<?php echo $_SESSION['contract']['deploy_amount'] ?>" placeholder="0"
                           <?php if($disabled) { ?>readonly<?php } ?>/>
                </div>
                <div class="col-12 sm:col-3">Parameters</div>
                <div class="col-12 sm:col-9">
                    <input type="text" name="deploy_params" value="<?php echo $_SESSION['contract']['deploy_params'] ?>"
                           placeholder="<?php echo implode(", ", $deploy_params) ?>"
                           <?php if($disabled) { ?>readonly<?php } ?>/>
                </div>
                <?php if(!empty($_SESSION['contract']['signature'])) { ?>
                    <div class="col-12 sm:col-3">Signature:</div>
                    <div class="col-12 sm:col-9">
                        <?php //if (empty($_SESSION['contract']['signature'])) { ?>
    <!--                        <button type="submit" name="sign_contract" class="p-1">Sign</button>-->
                        <?php //} else { ?>
                            <input type="text" name="deploy_signature" value="<?php echo $_SESSION['contract']['signature'] ?>" readonly/>
                        <?php //} ?>
                    </div>
                <?php } ?>
                <div class="col-12 sm:col-3">Contract address:</div>
                <div class="col-12 sm:col-9">
                    <div class="flex">
                        <?php if($virtual) {?>
                            <select name="deploy_address" <?php if ($_SESSION['contract']['status']=="deployed") { ?>disabled<?php } ?>>
                                <?php foreach($_SESSION['accounts'] as $account) { ?>
                                    <option value="<?php echo $account['address'] ?>" <?php if ($account['address']==$_SESSION['deploy_address']) { ?>selected="selected"<?php } ?>><?php echo $account['address'] ?></option>
                                <?php } ?>
                            </select>
                        <?php }else {?>
                            <input type="text" value="<?php echo @$_SESSION['deploy_address'] ?>" class="p-1"
                                   name="deploy_address" <?php if($disabled) { ?>readonly<?php } ?>>
                            <?php if (!$disabled) { ?>
                                <button type="button" onclick="newContractAddress()">New</button>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
                <div class="col-12 sm:col-3"></div>
                <div class="col-12 sm:col-9">
                <?php if (!$disabled) { ?>
                    <button type="submit" name="deploy" class="p-1" <?php if (@$_SESSION['contract']['status']=="deployed") { ?>disabled<?php } ?>>Deploy</button>
                <?php } ?>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
    <hr/>

	<?php if (@$_SESSION['contract']['status']=="deployed") { ?>
            <div>
        Interface
        <br/>Smart contract:
        <a target="_blank" href="<?php echo NODE_URL ?>/apps/explorer/address.php?address=<?php echo $_SESSION['contract']['address'] ?>">
            <?php echo $_SESSION['contract']['address'] ?>
        </a>
        <br/>Balance: <?php echo $_SESSION['contract']['balance'] ?>
            </div>
        <div class="grid if-tabs">
            <div class="col text-center if-tab <?php if(!isset($_SESSION['interface_tab']) || @$_SESSION['interface_tab']=="methods") { ?>sel-tab<?php } ?>">
                <a href="#" style="color: #ec7474" onclick="showInterfaceTab(this,'methods'); return false">Methods</a></div>
            <div class="col text-center if-tab <?php if(@$_SESSION['interface_tab']=="views") { ?>sel-tab<?php } ?>">
                <a href="#" style="color: #fff" onclick="showInterfaceTab(this,'views'); return false">Views</a></div>
            <div class="col text-center if-tab <?php if(@$_SESSION['interface_tab']=="properties") { ?>sel-tab<?php } ?>">
                <a href="#" style="color: #fff" onclick="showInterfaceTab(this,'properties'); return false">Properties</a></div>
        </div>

        <div style="display: <?php if (!isset($_SESSION['interface_tab']) || $_SESSION['interface_tab']=="methods") { ?>block<?php } else { ?>none<?php } ?>" class="tab" name="methods">
            <div class="grid grid-nogutter row">
                <div class="col-12 sm:col-3">
                    Type
                </div>
                <div class="col-12 sm:col-9 px-2">
                    <input onclick="this.form.submit()" type="radio" name="method_type" id="method_type_exec" value="exec" style="width: auto" <?php if (!isset($_SESSION['method_type']) || @$_SESSION['method_type']=="exec") { ?>checked<?php } ?>/> Exec
                    <input onclick="this.form.submit()" type="radio" name="method_type" id="method_type_send" value="send" style="width: auto" <?php if (@$_SESSION['method_type']=="send") { ?>checked<?php } ?>/> Send
                </div>
                <div class="col-12 sm:col-3">
                    Address
                </div>
                <div class="col-12 sm:col-9 px-2">
                    <?php if($virtual) { ?>
                        <?php if (@$_SESSION['method_type']=="exec") { ?>
                            <input type="text" value="<?php echo $_SESSION['contract']['address'] ?>" class="p-1" name="fn_address" placeholder="<?php echo $_SESSION['contract']['address'] ?>"/>
                        <?php } else { ?>
                            <select name="fn_address">
                                <?php foreach($_SESSION['accounts'] as $account) { ?>
                                    <option value="<?php echo $account['address'] ?>"
                                            <?php if (@$_SESSION['fn_address']==$account['address']) { ?>selected="selected"<?php } ?>>
                                        <?php echo $account['address'] ?>
                                        <?php if ($account['address'] == @$_SESSION['contract']['address']) { ?> (SmartContract)<?php } ?>
                                    </option>
                                <?php } ?>
                            </select>
                        <?php } ?>
                    <?php } else { ?>
                        <input type="text" value="<?php echo @$_SESSION['fn_address'] ?>" class="p-1" name="fn_address" placeholder="<?php echo $_SESSION['contract']['address'] ?>"/>
                    <?php } ?>
                    <?php
                    if(!empty($_SESSION['fn_address'])) {
                        $fn_address_balance = api_get( "/api.php?q=getBalance&address=".$_SESSION['fn_address']);
                    }
                    ?>
                </div>
                <div class="col-12 sm:col-3">
                    Amount
                </div>
                <div class="col-12 sm:col-9  px-2">
                    <input type="text" value="<?php echo @$_SESSION['amount'] ?>" class="p-1" name="amount" placeholder="Amount"/>
                </div>
            </div>
	        <?php if(is_array($_SESSION['contract']['interface']['methods'])) {
                foreach ($_SESSION['contract']['interface']['methods'] as $method) { ?>
                <div class="grid grid-nogutter">
                    <div class="col-12 sm:col-3">
                        <button type="submit" class="p-1 w-full" data-call="<?php echo $method['name'] ?>" name="exec_method[<?php echo $method['name'] ?>]"><?php echo $method['name'] ?></button>
                    </div>
                    <div class="col-12 sm:col-9  px-2">
                        <?php if (count($method['params']) > 0) { ?>
                            <input type="text" value="<?php echo @$_SESSION['params'][$method['name']] ?>" class="p-1" name="params[<?php echo $method['name'] ?>]"
                                   placeholder="<?php echo implode(",", $method['params']) ?>"/>
                        <?php } ?>
                    </div>
                </div>
	        <?php } } ?>
        </div>


        <div style="display: <?php if (@$_SESSION['interface_tab']=="views") { ?>block<?php } else { ?>none<?php } ?>" class="tab" name="views">
	        <?php if(is_array($_SESSION['contract']['interface']['views'])) {
                foreach ($_SESSION['contract']['interface']['views'] as $method) {
		        $name = $method['name'];
		        ?>
                <div class="grid grid-nogutter">
                    <div class="col-12 sm:col-3">
                        <button type="submit" class="p-1 w-full" name="view_method[<?php echo $name ?>]"><?php echo $name ?></button>
                    </div>
                    <div class="col-12 sm:col-9 flex align-items-center align-content-between  px-2">
                        <?php if (count($method['params']) > 0) { ?>
                            <input type="text" class="flex-grow-1 p-1" value="" name="params[<?php echo $name ?>]" placeholder="<?php echo implode(",", $method['params']) ?>"/>
                        <?php } ?>
                        <div class="flex-grow-1 px-5 white-space-normal">
                            <?php echo @$_SESSION['view_method_val'][$name] ?>
                        </div>
                        <?php if (isset($_SESSION['view_method_val'][$name])) { ?>
                            <button type="submit" class="flex-shrink-1 p-1" name="clear_view_method_val[<?php echo $name ?>]">x</button>
                        <?php } ?>
                    </div>
                </div>
	        <?php } }  ?>
        </div>

        <div style="display: <?php if (@$_SESSION['interface_tab']=="properties") { ?>block<?php } else { ?>none<?php } ?>" class="tab" name="properties">
	        <?php if(is_array($_SESSION['contract']['interface']['properties'])) {
                foreach ($_SESSION['contract']['interface']['properties'] as $property) {
		        $name = $property['name'];
		        $type = @$property['type'];
		        ?>
                <div class="grid grid-nogutter">
                    <div class="col-12 sm:col-3">
                        <button type="submit" class="p-1 w-full" name="get_property_val[<?php echo $name ?>]"><?php echo $name ?></button>
                    </div>
                    <div class="col-12 sm:col-9 flex align-items-center align-content-between px-2">
                        <?php if($type == "map") { ?>
                            <input type="text" class="flex-grow-1 p-1" name="property_key[<?php echo $name ?>]" value="<?php echo @$_SESSION['property_key'][$name] ?>"
                                   placeholder="Key"/>
                        <?php } ?>
                        <div class="flex-grow-1 px-5">
                            <?php echo @$_SESSION['get_property_val'][$name] ?>
                        </div>
                        <?php if (isset($_SESSION['get_property_val'][$name])) { ?>
                            <button type="submit" class="flex-shrink-1 p-1" name="clear_property_val[<?php echo $name ?>]">x</button>
                        <?php } ?>
                    </div>
                </div>
	        <?php } } ?>
        </div>

<!--        <pre>-->
<!--            --><?php //print_r($interface) ?>
<!--        </pre>-->

    <?php } ?>

        <hr/>
    <div class="mt-3">
        Transactions
        <div style="overflow-x: auto; scrollbar-color: auto; scrollbar-width: auto; max-height: 30vh;">
        <table class="table table-striped table-condensed">
            <thead>
            <tr>
                <th>height</th>
                <th>Source</th>
                <th>Destination</th>
                <th>Type</th>
                <th>Value</th>
                <th>Fee</th>
                <th>Method</th>
                <th>Params</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($transactions as $ix=>$tx) { ?>
                <tr>
                    <td><?php echo $virtual ? $ix : (empty($tx['block']) ? "mempool" : $tx['height']) ?></td>
                    <td><?php echo @$tx['src'] ?></td>
                    <td><?php echo $tx['dst'] ?></td>
                    <td><?php
                        if(empty($tx['block'])) {
                            $type = $tx['type_value'];
                        } else {
                            $type = $tx['type'];
                        }
                        if($type==TX_TYPE_SC_CREATE) echo "Deploy";
                        if($type==TX_TYPE_SC_EXEC) echo "Exec";
                        if($type==TX_TYPE_SC_SEND) echo "Send";
                        ?>
                    </td>
                    <td><?php echo $tx['val'] ?></td>
                    <td><?php echo $tx['fee'] ?></td>
                    <td><?php
                        if($tx['type']==TX_TYPE_SC_CREATE) {
                            echo "deploy";
                        }
                        if($tx['type']==TX_TYPE_SC_EXEC || $tx['type']==TX_TYPE_SC_SEND) {
                            $data = json_decode(base64_decode($tx['message']),true);
                            echo $data['method'];
                        }
                        ?>
                    </td>
                    <td><?php
                        if($tx['type']==TX_TYPE_SC_CREATE) {
                            $data= json_decode(base64_decode($tx['data']), true);
                            echo json_encode($data['params']);
                        }
                        if($tx['type']==TX_TYPE_SC_EXEC || $tx['type']==TX_TYPE_SC_SEND) {
                            $data = json_decode(base64_decode($tx['message']),true);
                            echo json_encode($data['params']);
                        }
                        ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    </div>
    <?php
    if(isset($_SESSION['contract']['address'])) {
        $state=SmartContractEngine::getState($_SESSION['contract']['address']);
    }
        ?>
    <div class="mt-3">
            State:
        <pre><?php print_r($state); ?></pre>
    </div>
	<input type="hidden" name="msg"/>
	<input type="hidden" name="date"/>
</form>




<link rel="stylesheet" href="https://unpkg.com/primeflex@3.1.2/primeflex.css">

<script src="<?php echo NODE_URL ?>/apps/common/js/jquery.min.js"></script>
<script src="<?php echo NODE_URL ?>/apps/common/js/phpcoin-crypto.js" type="text/javascript"></script>
<script type="text/javascript">

    function onSubmit(event) {
        console.log(event)
        let formData = $("form").serialize()
        let submitter = $(event.submitter)
        formData += '&' + submitter.attr('name') + '=' + submitter.val()
        event.preventDefault();

        $("#SBRIGHT #phpcoin-sb").css('opacity', 0.5)

        echo({
            url: '/phpcoin-sb.php',
            data: {
                formData: formData,
                ajax: true
            },
            settled: function(status, reply) {
                let tmp = $('<div style="display:none"><div>')
                tmp.html(reply)
                $("body").append(tmp)
                let sb = tmp.find("#phpcoin-sb")
                let h = sb.html()
                tmp.remove()
                $("#SBRIGHT #phpcoin-sb").html(h)
                $("#SBRIGHT #phpcoin-sb").css('opacity', 1)
                // if (status !== 'success') toast(status, reply);
                // if (hidden) return;
                // // self.displayStatus(reply);
                // toast(status, 'Setting "' + key + '" saved.');

            }
        });

        if($(event.submitter).data("call")) {
            //let method = $(event.submitter).data("call")
            //let params = []
            //if($("form [name='params["+method+"]']").length === 1) {
            //    params =  $("form [name='params["+method+"]']").val().trim()
            //    params = params.split(",")
            //    params = params.map(function (item) { return item.trim() })
            //    console.log(params)
            //}
            //let msg = JSON.stringify({method, params})
            //msg = btoa(msg)
            //let privateKey = $("form [name=private_key]").val().trim()
            //let amount = $("[name=amount").val();
            //signTx(privateKey, amount, <?php //echo TX_SC_EXEC_FEE ?>//, msg, <?php //echo TX_TYPE_SC_EXEC ?>//);
        }
    }

    function signTx(privateKey, amount, fee, msg, type) {
        amount = Number(amount).toFixed(8)
        fee = Number(fee).toFixed(8)
        let dst = $("form [name=sc_address]").val().trim()
        let date = Math.round(new Date().getTime()/1000)
        let data = '<?php echo CHAIN_ID ?>' + amount + '-' + fee + '-' + dst + '-' + msg + '-' + type + '-'
            + '<?php echo @$public_key ?>' + '-' + date
        let sig = sign(data, privateKey)
        console.log(data, sig)
        $("form [name=signature]").val(sig)
        $("form [name=msg]").val(msg)
        $("form [name=date]").val(date)
    }


    function showInterfaceTab(el, name) {
        $(".tab").hide();
        $(".if-tab").removeClass("sel-tab");
        $(el).parent().addClass("sel-tab");
        $(".tab[name="+name+"]").show();
    }

    function newContractAddress() {
        let account = generateAccount();
        console.log(account)
        $("[name=deploy_address]").val(account.address)
        localStorage.setItem("pk_"+account.address, account.privateKey)
    }

    <?php if (!$virtual && isset($_GET['deploy'])) {
        $d=1;
        ?>
    $(function() {
        $("[name=deploy]").click();
    });
    <?php } ?>

	    <?php if(isset($_SESSION['msg'])) { ?>
	    <?php foreach ($_SESSION['msg'] as $msg) {
            $text = $msg['text'];
	        $text = str_replace("'","\'", $text);
	        $text = str_replace("\n", " ", $text);
            ?>
            alert('<?php echo $text ?>');
	    <?php } ?>
	    <?php unset($_SESSION['msg']); } ?>

</script>

<style>
    #SBRIGHT > .content {
        overflow-y: auto;
    }
    #SBRIGHT #last_login {
        display: none;
    }
    /*#SBRIGHT {*/
    /*    width: */<?php //echo $settings['sidebars.sb-right-width'] ?>/*;*/
    /*    user-select: auto;*/
    /*}*/
    #SBRIGHT .if-tabs {
        margin-top: 10px;
        margin-bottom: 10px;
    }
    #SBRIGHT .if-tabs .if-tab {
        border-bottom: 1px solid #fff;
        padding: 0;
    }
    #SBRIGHT .if-tabs .if-tab a {
        margin: 0;
        padding: 10px !important;
        display: block !important;
    }
    #SBRIGHT .if-tabs .if-tab.sel-tab {
        border: 1px solid #fff;
        border-bottom: none;
    }
    #SBRIGHT #phpcoin-sb a {
        display: revert;
        margin: unset;
        color: inherit;
        padding: unset;
    }
    #workspace #ACTIVE, #workspace #BOTTOM {
        width: auto !important;
        min-width: 10px;
    }

    @media (max-width: 576px) {
        #workspace #SBLEFT {
            width:80vw;
        }
        #workspace #SBRIGHT {
            width:80vw;
        }
        #workspace #ACTIVE, #workspace #BOTTOM {
            width: auto !important;
            min-width: 10px;
        }
    }
</style>




