<?php

error_reporting(E_ALL^E_NOTICE);
require_once '/home/marko/web/phpcoin/node/include/init.inc.php';

$engines = [
	"virtual" => [
		"name" => "Virtual",
        "NODE_URL" => "http://spectre:8000",
	],
	"local" => [
		"name" => "Local",
		"NODE_URL" => "http://spectre:8000",
	],
	"testnet" => [
		"name" => "Testnet",
		"NODE_URL" => "https://node1.phpcoin.net",
	],
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
	header("location: /atheos");
	exit;
}

if(isset($_GET['signature_data'])) {
    $signature_data = json_decode(base64_decode($_GET['signature_data']), true);
    if(isset($signature_data['signature'])) {
        $_SESSION['contract']['signature']=$signature_data['signature'];
    }
    header("location: /atheos");
    exit;
}

if(isset($_GET['response'])) {


}

if(isset($_POST['reset'])) {
	$_SESSION['account']=null;
	$_SESSION['accounts']=[];
	$_SESSION['contract']=null;
	$_SESSION['sc_account']=null;
	$_SESSION['transactions'] = [];
	$_SESSION['property_key'] = null;
	$_SESSION['get_property_val'] = null;
	$_SESSION['call_method_val'] = null;
	$_SESSION['engine'] = 'virtual';
	$_SESSION['deploy_amount'] = null;
	$_SESSION['deploy_params'] = null;
	$projectName = "example";
	$projectPath = session_id();
	SESSION("projectPath", $projectPath);
	SESSION("projectName", $projectName);
	header("location: ". $_SERVER['REQUEST_URI']);
	exit;
}

if(isset($_POST['compile'])) {
    $phar_file = ROOT . "/tmp/sc/".uniqid().".phar";

    $source = $_POST['source'];
    if($source == "folder") {
        $file = WORKSPACE . "/" . session_id()."/index.php";
    } else {
        $file = substr($source, 5);
        $file = WORKSPACE . "/" . session_id().$file;
    }
    $_SESSION['source']=$source;

    $_SESSION['contract']['code']=null;
    $res = SmartContract::compile($file, $phar_file, $err);
    if(!$res) {
        $_SESSION['msg']=[['icon'=>'error', 'text'=>'Error compiling smart contract: '.$err]];
        header("location: ".$_SERVER['REQUEST_URI']);
        exit;
    }
    $code=base64_encode(file_get_contents($phar_file));
    $res = SmartContractEngine::verifyCode($code, $error);
    if(!$res) {
        $_SESSION['msg']=[['icon'=>'error', 'text'=>'Error verify smart contract: '.$error]];
        header("location: ".$_SERVER['REQUEST_URI']);
        exit;
    }
    $_SESSION['contract']['code']=$code;
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}


if(isset($_POST['sign_contract'])) {

    $request_code = uniqid();
    $_SESSION['request_code']=$request_code;
    $redirect = urlencode("/atheos/?");

    $address=$_POST['address'];
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


if(isset($_POST['deploy'])) {
	$address = $_POST['address'];
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

	$signature = $_POST['deploy_signature'];
	$msg = $signature;
	$date = $_POST['date'];
	$compiled_code = $_POST['compiled_code'];
    $amount=$_POST['deploy_amount'];
    if(empty($amount)) $amount=0;

    $_SESSION['deploy_params']=$_POST['deploy_params'];
    $post_deploy_params = $_POST['deploy_params'];
    $arr=explode(",",$post_deploy_params);
    $deploy_params = [];
    foreach ($arr as $item) {
        $item = trim($item);
        if(strlen($item) == 0) continue;
        $deploy_params[]=$item;
    }

    $data = [
        "code"=>$compiled_code,
        "amount"=>num($amount),
        "params"=>$deploy_params
    ];

    $txdata = base64_encode(json_encode($data));


    $_SESSION['deploy_amount']=$_POST['deploy_amount'];
    $deploy_amount = floatval($_POST['deploy_amount']);

	$transaction = new Transaction($public_key,$deploy_address,$deploy_amount,TX_TYPE_SC_CREATE,$date, $msg, TX_SC_CREATE_FEE);
//	$transaction->signature = $signature;
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

		$interface = SmartContractEngine::getInterface($deploy_address);

		$_SESSION['contract']=[
			"address"=>$deploy_address,
			"height"=>1,
			"code"=>$compiled_code,
			"signature"=>$signature,
            "interface"=>$interface
		];

		header("location: ".$_SERVER['REQUEST_URI']);

	} else {

		$request_code = uniqid();
		$_SESSION['auth_request_code']=$request_code;
		$redirect = urlencode("/atheos/?");
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

        $url=NODE_URL . "/dapps.php?url=".MAIN_DAPPS_ID."/gateway/approve.php?&app=Faucet&request_code=$request_code&&redirect=$redirect&tx=$tx";

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


		$url=NODE_URL . "/dapps.php?url=".MAIN_DAPPS_ID."/gateway/approve.php?&app=Faucet&request_code=$request_code&tx=$tx&redirect=$redirect";
		header("location: $url");
		exit;


		$res = api_post("/api.php?q=send",
			array("dst" => $sc_address, "val" => 0, "signature" => $signature,
				"public_key" => $public_key, "type" => TX_TYPE_SC_CREATE,
				"message" => $msg, "date" => $date, "fee" => $transaction->fee, "data" => $transaction->data));


		$hash = $res['data'];
		if(!$hash) {
			$_SESSION['msg']=[['icon'=>'error', 'text'=>'Transaction can not be sent: '.$res['error']]];
			header("location: ".$_SERVER['REQUEST_URI']);
			exit;
		} else {
		    $_SESSION['deploy_tx']=$hash;
			$_SESSION['msg']=[['icon'=>'success', 'text'=>'Transaction sent! Id of transaction: '.$hash]];
			header("location: ".$_SERVER['REQUEST_URI']);
			exit;
		}
	}

}


if(isset($_POST['save'])) {
    $engine = $_POST['engine'];
	$_SESSION['engine'] = $engine;
    $virtual = $engine == "virtual";
    if(!$virtual) {
//        unset($_SESSION['account']);
//        unset($_SESSION['sc_account']);
//	    $_SESSION['account']['address'] = $_POST['address'];
//	    $_SESSION['account']['private_key'] = $_POST['private_key'];
//	    $_SESSION['sc_account']['address'] = $_POST['sc_address'];
    }
	header("location: ".$_SERVER['REQUEST_URI']);
	exit;
}

if(isset($_POST['set_contract'])) {
    $contract = $_POST['contract'];
    $address = $_SESSION['account']['address'];
    $deployedContracts = api_get("/api.php?q=getDeployedSmartContracts&address=$address");
    $_SESSION['contract']=null;
    foreach ($deployedContracts as $deployedContract) {
        if($deployedContract['address']==$contract) {
            $_SESSION['contract']=$deployedContract;
            $_SESSION['contract']['deployed']=true;
            $_SESSION['contract']['interface'] = SmartContractEngine::getInterface($_SESSION['contract']['address']);
            break;
        }
    }
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

if(isset($_POST['get_source'])) {
	$code = $_SESSION['contract']['code'];
    $code=base64_decode($code);
	$phar_file = ROOT . "/tmp/".uniqid().".phar";
	file_put_contents($phar_file, $code);

    ob_end_clean();
	header("Content-Description: File Transfer");
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"". basename($phar_file) ."\"");

	readfile ($phar_file);
	exit();
}

if(isset($_POST['exec_method'])) {
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
        $type=TX_TYPE_SC_EXEC;
        $public_key = $_SESSION['account']['public_key'];
        $src=$address;
        $dst=$fn_address;
    } else if ($method_type == "send") {
        $smartContract = api_get("/api.php?q=getSmartContract&address=".$_SESSION['account']['address']);
        if(!$smartContract) {
            $_SESSION['msg']=[['icon'=>'error', 'text'=>'Method connect smart contract wallet for call send method']];
            header("location: ".$_SERVER['REQUEST_URI']);
            exit;
        }
        $type=TX_TYPE_SC_SEND;
        $public_key = $_SESSION['account']['public_key'];
        $src=$_SESSION['account']['address'];
        $dst=$fn_address;
    }

	$call_method = array_keys($_POST['exec_method'])[0];

	$date = $_POST['date'];
	$msg = $_POST['msg'];
	$signature = $_POST['signature'];
	$transaction = new Transaction($public_key,$dst,$amount,$type,$date, $msg, TX_SC_EXEC_FEE);
	$transaction->signature = $signature;

	if($virtual) {
		$hash = $transaction->hash();

		SmartContractEngine::$virtual = true;
		SmartContractEngine::$smartContract = $_SESSION['contract'];
		$params = [];
		if(!empty($msg)) {
			$msg = base64_decode($msg);
			$msg = json_decode($msg,true);
			$params = $msg['params'];
		}
		$res = SmartContractEngine::exec($transaction, $call_method, 0, $params, $err);
		if(!$res) {
			$_SESSION['msg']=[['icon'=>'error', 'text'=>'Method can not be executed: '.$err]];
			header("location: ".$_SERVER['REQUEST_URI']);
			exit;
		}
        $_SESSION['accounts'][$src]['balance'] = $_SESSION['accounts'][$src]['balance'] - (TX_SC_EXEC_FEE + $amount);
        $_SESSION['accounts'][$dst]['balance'] = $_SESSION['accounts'][$dst]['balance'] + $amount;

        $_SESSION['transactions'][]=$transaction->toArray();

	} else {

        $params = [];
        if(isset($_POST['params'][$call_method])) {
            $post_params = $_POST['params'][$call_method];
            $post_params = explode(",", $post_params);
            foreach($post_params as &$item) {
                $item = trim($item);
                if(strlen($item)>0) {
                    $params[]=$item;
                }
            }
        }

        $data = [
            "method" => $call_method,
            "params" => $params
        ];
        
        $msg=base64_encode(json_encode($data));

        $request_code = uniqid();
        $_SESSION['auth_request_code']=$request_code;
        $redirect = urlencode("/atheos/?");
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

		$res = api_post("/api.php?q=send",
			array("dst" => $sc_address, "val" => $amount, "signature" => $signature,
				"public_key" => $public_key, "type" => $transaction->type,
				"message" => $msg, "date" => $date, "fee" => $transaction->fee, "data" => $transaction->data));

		$hash = $res['data'];
		if(!$hash) {
			$_SESSION['msg']=[['icon'=>'error', 'text'=>'Transaction can not be sent: '.$res['error']]];
			header("location: ".$_SERVER['REQUEST_URI']);
			exit;
		} else {
			$_SESSION['msg']=[['icon'=>'success', 'text'=>'Transaction sent! Id of transaction: '.$hash]];
			header("location: ".$_SERVER['REQUEST_URI']);
			exit;
		}
	}

    $_SESSION['interface_tab']="methods";
	header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

if(isset($_POST['call_method'])) {
	$account = $_POST['address'];
    $sc_address = $_SESSION['contract']['address'];
	$call_method = array_keys($_POST['call_method'])[0];

    $params = [];
    if(isset($_POST['params'][$call_method])) {
        $call_method_params = explode(",", $_POST['params'][$call_method]);
        foreach($call_method_params as &$item) {
            $item = trim($item);
            if(strlen($item)>0) {
                $params[]=$item;
            }
        }
    }
	if($virtual) {
		SmartContractEngine::$virtual = true;
		SmartContractEngine::$smartContract = $_SESSION['contract'];
	    $res = SmartContractEngine::call($sc_address, $call_method, $params, $err);
	} else {
		$params = base64_encode(json_encode($params));
        $sc_address = $_SESSION['contract']['address'];
        $res = api_get("/api.php?q=getSmartContractView&address=$sc_address&method=$call_method&params=$params");
    }
	if($err) {
		$_SESSION['msg']=[['icon'=>'error', 'text'=>'View can not be executed: '.$err]];
		header("location: ".$_SERVER['REQUEST_URI']);
		exit;
	}
	$_SESSION['call_method_val'][$call_method]=$res;
	$_SESSION['interface_tab']="views";
	header("location: ".$_SERVER['REQUEST_URI']);
	exit;
}

if(isset($_POST['get_property_val'])) {
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
	    $val = SmartContractEngine::SCGet($sc_address, $property, $mapkey);
	} else {
		$val = api_get("/api.php?q=getSmartContractProperty&address=$sc_address&property=$property&key=$mapkey");
    }
	$_SESSION['get_property_val'][$property]=$val;
	$_SESSION['interface_tab']="properties";
	header("location: ".$_SERVER['REQUEST_URI']);
	exit;
}

if(isset($_POST['clear_property_val'])) {
	$property = array_keys($_POST['clear_property_val'])[0];
	unset($_SESSION['get_property_val'][$property]);
	unset($_SESSION['property_key'][$property]);
	header("location: ".$_SERVER['REQUEST_URI']);
	exit;
}

if(isset($_POST['clear_call_method_val'])) {
	$property = array_keys($_POST['clear_call_method_val'])[0];
	unset($_SESSION['call_method_val'][$property]);
	header("location: ".$_SERVER['REQUEST_URI']);
	exit;
}


if(isset($_POST['create_new_sc_address'])) {
	$_SESSION['sc_account'] = api_get( "/api.php?q=generateAccount");
	header("location: ". $_SERVER['REQUEST_URI']);
	exit;
}

if(isset($_POST['wallet_auth'])) {
	$request_code = uniqid();
	$_SESSION['auth_request_code']=$request_code;
	$redirect = urlencode("/atheos/?");
	$url=NODE_URL."/dapps.php?url=".MAIN_DAPPS_ID."/gateway/auth.php?app=Athoes&request_code=$request_code&redirect=$redirect";
	header("location: $url");
	exit;
}

if(isset($_POST['logout'])) {
	unset($_SESSION['account']);
	unset($_SESSION['accounts']);
	header("location: ".$_SERVER['REQUEST_URI']);
	exit;
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
	        $account['balance'] =100;
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
    }
    $debug="&".XDEBUG;
    $smartContract = api_get("/api.php?q=getSmartContract&address=".$_SESSION['account']['address'].$debug);
    if($smartContract) {
        $_SESSION['contract']=$smartContract;
        $_SESSION['contract']['deployed']=true;
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
        $_SESSION['contract']['interface'] = api_get("/api.php?q=getSmartContractInterface&address=".$_SESSION['contract']['address']);
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

$folder = WORKSPACE . "/" . session_id();
$cmd = "find $folder -type f -name '*.php'";
$output = Common::execute($cmd);
$output = $output["text"];

$list = explode("\n", $output);
$files = [];
foreach($list as $item) {
    if(strpos($item, $folder) !== false) {
	    $files[]=str_replace($folder, "", $item);
    }
}

require_once('components/settings/class.settings.php');
$activeUser = SESSION("user");
$Settings = new Settings($activeUser);
$settings = @$_SESSION['settings'];
?>

<form class="p-2" method="post" action="" onsubmit="onSubmit(event)">

    <div class="flex align-items-center align-content-between">
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
        <div class="col-3">Engine:</div>
        <div class="col-9">
            <select name="engine" class="p-1">
                <?php foreach ($engines as $engine_id => $engine_config) { ?>
                    <option value="<?php echo $engine_id ?>" <?php if($engine_id == $engine) { ?>selected="selected"<?php } ?>><?php echo $engine_config['name'] ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

	<?php if ($virtual) { ?>
        <div class="grid align-items-center grid-nogutter">
            <div class="col-3">
                Wallet address:
            </div>
            <div class="col-9">
                <select name="address" onchange="this.form.submit()">
                    <?php foreach($_SESSION['accounts'] as $account) { ?>
                        <option value="<?php echo $account['address'] ?>"
                                <?php if ($_SESSION['account']['address']==$account['address']) { ?>selected="selected"<?php } ?>><?php echo $account['address'] ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-3">
                Private key:
            </div>
            <div class="col-9">
                <input type="password" value="<?php echo $private_key ?>" class="p-1" name="private_key" <?php if($virtual) { ?>readonly="readonly"<?php } ?>/>
            </div>
            <div class="col-3">
                Balance:
            </div>
            <div class="col-9">
				<?php echo num($balance) ?> (<?php echo $_SESSION['account']['address'] ?>)
            </div>
        </div>
    <?php } else { ?>
        <div class="grid align-items-center grid-nogutter">
            <div class="col-3">
                Wallet:
	            <?php if (isset($_SESSION['account'])) { ?>
                    <br/>
                    <button name="logout" type="submit" class="p-1">Logout</button>
                <?php } ?>
            </div>
            <div class="col-9">
                <?php if (isset($_SESSION['account'])) { ?>
                    <?php echo $_SESSION['account']['address'] ?>
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
            <div class="col-3">Contract:</div>
            <div class="col-9">
                <div class="flex">
                    <select name="contract" class="p-1 w-auto">
                        <option value="">Select...</option>
                        <?php foreach($deployedContracts as $contract) { ?>
                            <option value="<?php echo $contract['address'] ?>" <?php if ($contract['address']==@$_SESSION['contract']['address']) { ?>selected<?php } ?>><?php echo $contract['address'] ?></option>
                        <?php } ?>
                    </select>
                    <button name="set_contract" class="p-1">Set contract</button>
                </div>
            </div>
            <?php if ($_SESSION['contract']['deployed']) { ?>
                <div class="col-3">Height:</div>
                <div class="col-9"><?php echo $_SESSION['contract']['height'] ?></div>
                <div class="col-3">Code:</div>
                <div class="col-9">
                    <textarea readonly><?php echo $_SESSION['contract']['code'] ?></textarea>
                </div>
                <div class="col-3">Signature:</div>
                <div class="col-9">
                    <input type="text" value="<?php echo $_SESSION['contract']['signature'] ?>" readonly/>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if(!@$_SESSION['contract']['deployed']) { ?>
        <hr/>
        New contract
        <div class="grid align-items-center grid-nogutter">
            <div class="col-3">Source:</div>
            <div class="col-9">
                <div class="flex">
                    <select name="source" class="p-1">
                        <option value="folder" <?php if(@$_SESSION['source']=="folder") { ?>selected="selected"<?php } ?>>Whole folder</option>
                        <?php foreach($files as $file) { ?>
                            <option value="file:<?php echo $file ?>" <?php if(@$_SESSION['source']=="file:$file") { ?>selected="selected"<?php } ?>><?php echo $file ?></option>
                        <?php } ?>
                    </select>
                    <button type="submit" name="compile" class="p-1">Compile</button>
                </div>
            </div>
        </div>
        <?php if (!empty($compiled_code)) { ?>
            <div class="grid align-items-center grid-nogutter">
                <div class="col-3">
                    Compiled code:
                    <br/>
                    <button type="submit" name="get_source">Source</button>
                </div>
                <div class="col-9">
                    <textarea name="compiled_code" rows="3" cols="30" readonly="readonly"><?php echo $compiled_code ?></textarea>
                </div>
            </div>
        <?php } ?>

        <?php if (!empty($compiled_code)) { ?>
            <hr/>
            <h4><strong>Deploy contract</strong></h4>
            <div class="grid align-items-center grid-nogutter">
                <div class="col-3">Amount</div>
                <div class="col-9">
                    <input type="text" name="deploy_amount" value="<?php echo $_SESSION['deploy_amount'] ?>" placeholder="Amount"/>
                </div>
                <div class="col-3">Parameters</div>
                <div class="col-9">
                    <input type="text" name="deploy_params" value="<?php echo $_SESSION['deploy_params'] ?>" placeholder="Param1, Param2, ..."/>
                </div>
                <div class="col-3">Signature:</div>
                <div class="col-9">
                    <?php //if (empty($_SESSION['contract']['signature'])) { ?>
                        <button type="submit" name="sign_contract" class="p-1">Sign</button>
                    <?php //} else { ?>
                        <input type="text" name="deploy_signature" value="<?php echo $_SESSION['contract']['signature'] ?>" readonly/>
                    <?php //} ?>
                </div>
                <?php if (!empty($_SESSION['contract']['signature'])) { ?>
                    <div class="col-3">Contract address:</div>
                    <div class="col-9">
                        <div class="flex">
                            <?php if($virtual) {?>
                                <select name="sc_address" onchange="this.form.submit()">
                                    <?php foreach($_SESSION['accounts'] as $account) { ?>
                                        <option value="<?php echo $account['address'] ?>" <?php if ($account['address']==$sc_address) { ?>selected="selected"<?php } ?>><?php echo $account['address'] ?></option>
                                    <?php } ?>
                                </select>
                            <?php }else {?>
                                <input type="text" value="<?php echo @$_SESSION['deploy_address'] ?>" class="p-1"
                                       name="deploy_address">
                            <?php } ?>
                        </div>
                    </div>
                    <div class="col-3"></div>
                    <div class="col-9">
                        <button type="submit" name="deploy" class="p-1">Deploy</button>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    <?php } ?>
    <hr/>

	<?php if (@$_SESSION['contract']['interface']) { ?>
            Interface
            <br/>Smart contract: <?php echo $_SESSION['contract']['address'] ?>
            <br/>Balance: <?php echo $_SESSION['contract']['balance'] ?>
        <div class="grid">
            <div class="col text-center"><a href="#" style="color: #fff" onclick="showInterfaceTab('methods'); return false">Methods</a></div>
            <div class="col text-center"><a href="#" style="color: #fff" onclick="showInterfaceTab('views'); return false">Views</a></div>
            <div class="col text-center"><a href="#" style="color: #fff" onclick="showInterfaceTab('properties'); return false">Properties</a></div>
        </div>

        <div style="display: <?php if (!isset($_SESSION['interface_tab']) || $_SESSION['interface_tab']=="methods") { ?>block<?php } else { ?>none<?php } ?>" class="tab" name="methods">
            <div class="grid grid-nogutter row">
                <div class="col-3">
                    Type
                </div>
                <div class="col-9 px-2">
                    <input type="radio" name="method_type" id="method_type_exec" value="exec" style="width: auto" <?php if (@$_SESSION['method_type']=="exec") { ?>checked<?php } ?>/> Exec
                    <input type="radio" name="method_type" id="method_type_send" value="send" style="width: auto" <?php if (@$_SESSION['method_type']=="send") { ?>checked<?php } ?>/> Send
                </div>
                <div class="col-3">
                    Address
                </div>
                <div class="col-9 px-2">
                    <input type="text" value="<?php echo @$_SESSION['fn_address'] ?>" class="p-1" name="fn_address" placeholder="Address"/>
                    <?php
                    if(!empty($_SESSION['fn_address'])) {
                        $fn_address_balance = api_get( "/api.php?q=getBalance&address=".$_SESSION['fn_address']);
                    }
                    ?>
                </div>
                <div class="col-3">
                    Balance
                </div>
                <div class="col-9  px-2"><?php echo $fn_address_balance; ?></div>
                <div class="col-3">
                    Amount
                </div>
                <div class="col-9  px-2">
                    <input type="text" value="<?php echo @$_SESSION['amount'] ?>" class="p-1" name="amount" placeholder="Amount"/>
                </div>
            </div>
	        <?php if(is_array($_SESSION['contract']['interface']['methods'])) {
                foreach ($_SESSION['contract']['interface']['methods'] as $method) { ?>
                <div class="grid grid-nogutter">
                    <div class="col-3">
                        <button type="submit" class="p-1 w-full" data-call="<?php echo $method['name'] ?>" name="exec_method[<?php echo $method['name'] ?>]"><?php echo $method['name'] ?></button>
                    </div>
                    <div class="col-9  px-2">
                        <?php if (count($method['params']) > 0) { ?>
                            <input type="text" value="" class="p-1" name="params[<?php echo $method['name'] ?>]" placeholder="<?php echo implode(",", $method['params']) ?>"/>
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
                    <div class="col-3">
                        <button type="submit" class="p-1 w-full" name="call_method[<?php echo $name ?>]"><?php echo $name ?></button>
                    </div>
                    <div class="col-9 flex align-items-center align-content-between  px-2">
                        <?php if (count($method['params']) > 0) { ?>
                            <input type="text" class="flex-grow-1 p-1" value="" name="params[<?php echo $name ?>]" placeholder="<?php echo implode(",", $method['params']) ?>"/>
                        <?php } ?>
                        <div class="flex-grow-1 px-5 white-space-normal">
                            <?php echo @$_SESSION['call_method_val'][$name] ?>
                        </div>
                        <?php if (isset($_SESSION['call_method_val'][$name])) { ?>
                            <button type="submit" class="flex-shrink-1 p-1" name="clear_call_method_val[<?php echo $name ?>]">x</button>
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
                    <div class="col-3">
                        <button type="submit" class="p-1 w-full" name="get_property_val[<?php echo $name ?>]"><?php echo $name ?></button>
                    </div>
                    <div class="col-9 flex align-items-center align-content-between px-2">
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
        <div style="overflow-x: auto; scrollbar-color: auto; scrollbar-width: auto;">
        <table class="table table-striped table-condensed">
            <thead>
            <tr>
                <th>height</th>
                <th>Source</th>
                <th>Destination</th>
                <th>Type</th>
                <th>Value</th>
                <th>Fee</th>
                <th>Message</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($transactions as $ix=>$tx) { ?>
                <tr>
                    <td><?php echo $virtual ? $ix : $tx['height'] ?></td>
                    <td><?php echo @$tx['src'] ?></td>
                    <td><?php echo $tx['dst'] ?></td>
                    <td><?php echo $tx['type'] ?></td>
                    <td><?php echo $tx['val'] ?></td>
                    <td><?php echo $tx['fee'] ?></td>
                    <td><?php if($tx['type']!=5) echo base64_decode($tx['message']) ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    </div>
	<input type="hidden" name="msg"/>
	<input type="hidden" name="date"/>
</form>

<link rel="stylesheet" href="https://unpkg.com/primeflex@3.1.2/primeflex.css">

<script src="<?php echo NODE_URL ?>/apps/common/js/jquery.min.js"></script>
<script src="<?php echo NODE_URL ?>/apps/common/js/web-miner.js" type="text/javascript"></script>
<script type="text/javascript">

    function onSubmit(event) {
        if ($(event.submitter).attr("name") === "deploy") {
            let compiled_code = $("form [name=compiled_code]").val().trim()
            let privateKey = $("form [name=private_key]").val().trim()
            let msg = sign(compiled_code, privateKey)
            let amount = $("form [name=deploy_amount]").val().trim()
            //signTx(privateKey, amount, <?php echo TX_SC_CREATE_FEE ?>, msg, <?php echo TX_TYPE_SC_CREATE ?>);
        }
        if($(event.submitter).data("call")) {
            let method = $(event.submitter).data("call")
            let params = []
            if($("form [name='params["+method+"]']").length === 1) {
                params =  $("form [name='params["+method+"]']").val().trim()
                params = params.split(",")
                params = params.map(function (item) { return item.trim() })
                console.log(params)
            }
            let msg = JSON.stringify({method, params})
            msg = btoa(msg)
            let privateKey = $("form [name=private_key]").val().trim()
            let amount = $("[name=amount").val();
            signTx(privateKey, amount, <?php echo TX_SC_EXEC_FEE ?>, msg, <?php echo TX_TYPE_SC_EXEC ?>);
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


    function showInterfaceTab(name) {
        $(".tab").hide();
        $(".tab[name="+name+"]").show();
    }

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
    #SBRIGHT {
        width: <?php echo $settings['sidebars.sb-right-width'] ?>;
        user-select: auto;
    }
</style>




