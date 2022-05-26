<?php

const COIN = "phpcoin";

const TX_TYPE_SC_CREATE = 5;
const TX_TYPE_SC_EXEC = 6;

const ROOT = '/home/marko/web/phpcoin/node';
const COIN_DECIMALS = 8;

$engines = [
	"virtual" => [
		"name" => "Virtual",
		"TX_SC_CREATE_FEE" => 10,
		"TX_SC_EXEC_FEE" => 0.001,
		"NETWORK_PREFIX" => "30",
		"NETWORK" => "testnet",
		"WALLET_VERSION" => "0.0.1",
		"SC_MAX_EXEC_TIME" => 5,
		"SC_MEMORY_LIMIT" => "128M",
		"NODE_URL" => "http://10.0.1.1:8001",
	],
	"testnet-local" => [
		"name" => "Testnet-local",
		"TX_SC_CREATE_FEE" => 10,
		"TX_SC_EXEC_FEE" => 0.001,
		"NETWORK_PREFIX" => "30",
		"NETWORK" => "testnet",
		"WALLET_VERSION" => "0.0.1",
		"SC_MAX_EXEC_TIME" => 5,
		"SC_MEMORY_LIMIT" => "128M",
		"NODE_URL" => "http://10.0.1.1:8001",
	],
//	"testnet" => [
//		"name"=>"Testnet"
//    ],
//	"mainnet" => [
//		"name"=>"Mainnet"
//	],
];



require_once ROOT . '/include/class/SmartContractEngine.php';
require_once ROOT . '/include/class/SmartContract.php';
require_once ROOT . '/include/class/Transaction.php';
require_once ROOT . '/include/class/Account.php';
require_once ROOT . '/include/functions.inc.php';

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
			"version"=> WALLET_VERSION,
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

$virtual = $engine == "virtual";

define("TX_SC_CREATE_FEE", @$engines[$engine]['TX_SC_CREATE_FEE']);
define("TX_SC_EXEC_FEE", @$engines[$engine]['TX_SC_EXEC_FEE']);
define("NETWORK_PREFIX", @$engines[$engine]['NETWORK_PREFIX']);
define("NETWORK", @$engines[$engine]['NETWORK']);
define("WALLET_VERSION", @$engines[$engine]['WALLET_VERSION']);
define("SC_MAX_EXEC_TIME", @$engines[$engine]['SC_MAX_EXEC_TIME']);
define("SC_MEMORY_LIMIT", @$engines[$engine]['SC_MEMORY_LIMIT']);
define("NODE_URL", @$engines[$engine]['NODE_URL']);

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
	$projectName = session_id();
	$projectPath = $projectName;
	SESSION("projectPath", $projectPath);
	SESSION("projectName", $projectName);
	header("location: ". $_SERVER['REQUEST_URI']);
	exit;
}


if(isset($_POST['deploy'])) {
	$address = $_POST['address'];
	if(empty($address)) {
		$_SESSION['msg']=[['icon'=>'error', 'text'=>'Wallet address is required']];
		header("location: ".$_SERVER['REQUEST_URI']);
		exit;
	}
	$sc_address = $_POST['sc_address'];
	if(empty($sc_address)) {
		$_SESSION['msg']=[['icon'=>'error', 'text'=>'Contract address is required']];
		header("location: ".$_SERVER['REQUEST_URI']);
		exit;
	}

    $smartContract = api_get("/api.php?getSmartContract&address=$sc_address");
	if($smartContract && !$virtual) {
		$_SESSION['msg']=[['icon'=>'error', 'text'=>'Smart contract with address already exists']];
		header("location: ".$_SERVER['REQUEST_URI']);
		exit;
	}

    if($virtual) {
	    $public_key = $_SESSION['account']['public_key'];
    } else {
        $public_key = api_get("/api.php?q=getPublicKey&address=$address");
        if(!$public_key) {
            $_SESSION['msg']=[['icon'=>'error', 'text'=>'Wallet address is not verified']];
            header("location: ".$_SERVER['REQUEST_URI']);
            exit;
        }
    }

	$signature = $_POST['signature'];
	$msg = $_POST['msg'];
	$date = $_POST['date'];
	$compiled_code = $_POST['compiled_code'];

	$transaction = new Transaction($public_key,$sc_address,0,TX_TYPE_SC_CREATE,$date, $msg, TX_SC_CREATE_FEE);
	$transaction->signature = $signature;
	$transaction->data = $compiled_code;

	if($virtual) {
		$hash = $transaction->hash();
		$_SESSION['transactions'][]=$transaction->toArray();

		SmartContractEngine::$virtual = true;
		SmartContractEngine::$smartContract = $_SESSION['contract'];
		$res = SmartContractEngine::deploy($transaction, 0, $err);

        if(!$res) {
	        $_SESSION['msg']=[['icon'=>'error', 'text'=>'Smart contract not deployed: '.$err]];
	        header("location: ".$_SERVER['REQUEST_URI']);
        } else {
	        $_SESSION['accounts'][$address]['balance']=$_SESSION['accounts'][$address]['balance'] - TX_SC_CREATE_FEE;
        }

		$interface = SmartContractEngine::getInterface($sc_address);

		$_SESSION['contract']=[
			"address"=>$sc_address,
			"height"=>1,
			"code"=>$compiled_code,
			"signature"=>$signature,
            "interface"=>$interface
		];

		header("location: ".$_SERVER['REQUEST_URI']);

	} else {

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

if(isset($_POST['compile'])) {
	$sc_address = $_POST['sc_address'];
	$phar_file = ROOT . "/tmp/sc/$sc_address.phar";

    $source = $_POST['source'];
    if($source == "folder") {
        $file = SESSION("projectPath")."/index.php";
    } else {
	    $file = substr($source, 5);
	    $file = SESSION("projectPath").$file;
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
	$res = SmartContractEngine::verifyCode($code, $error, $sc_address);
	if(!$res) {
		$_SESSION['msg']=[['icon'=>'error', 'text'=>'Error verify smart contract: '.$error]];
		header("location: ".$_SERVER['REQUEST_URI']);
		exit;
	}
	$_SESSION['contract']['code']=$code;
	header("location: ".$_SERVER['REQUEST_URI']);
	exit;
}

if(isset($_POST['save'])) {
	$_SESSION['engine'] = $_POST['engine'];
    if(!$virtual) {
	    $_SESSION['account']['address'] = $_POST['address'];
	    $_SESSION['account']['private_key'] = $_POST['private_key'];
	    $_SESSION['sc_account']['address'] = $_POST['sc_address'];
    }
	header("location: ".$_SERVER['REQUEST_URI']);
	exit;
}

if(isset($_POST['get_source'])) {
	$sc_address = $_POST['sc_address'];
	if($virtual) {
		$smartContract = $_SESSION['contract'];
	} else {
		$smartContract = api_get("/api.php?q=getSmartContract&address=$sc_address");
	}
	$code = $smartContract['code'];
	$phar_file = ROOT . "/tmp/$sc_address.phar";
	file_put_contents($phar_file, base64_decode($code));

    ob_end_clean();
	header("Content-Description: File Transfer");
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"". basename($phar_file) ."\"");

	readfile ($phar_file);
	exit();
}

if(isset($_POST['exec_method'])) {
	$account = $_POST['address'];
	$sc_address = $_POST['sc_address'];
    $amount = $_POST['amount'];
	$call_method = array_keys($_POST['exec_method'])[0];

	$public_key = $virtual ? $_SESSION['account']['public_key'] : api_get("/api.php?q=getPublicKey&address=$account");
	$date = $_POST['date'];
	$msg = $_POST['msg'];
	$signature = $_POST['signature'];
	$transaction = new Transaction($public_key,$sc_address,$amount,TX_TYPE_SC_EXEC,$date, $msg, TX_SC_EXEC_FEE);
	$transaction->signature = $signature;

	if($virtual) {
		$hash = $transaction->hash();
		$_SESSION['transactions'][]=$transaction->toArray();
		$_SESSION['sc_balance'] = $_SESSION['sc_balance'] + $amount;

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
		} else {
			$_SESSION['accounts'][$account]['balance'] = $_SESSION['accounts'][$account]['balance'] - (TX_SC_EXEC_FEE + $amount);
        }
	} else {

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
	$sc_address = $_POST['sc_address'];
	$compiled_code = $_POST['compiled_code'];
	$call_method = array_keys($_POST['call_method'])[0];
	$msg = $_POST['msg'];

    $params = [];
    if(isset($_POST['params'][$call_method])) {
        $params = $_POST['params'][$call_method];
        $params = explode(",", $params);
        foreach($params as &$item) {
            $item = trim($item);
        }
    }
	if($virtual) {
		SmartContractEngine::$virtual = true;
		SmartContractEngine::$smartContract = $_SESSION['contract'];
	    $res = SmartContractEngine::call($sc_address, $call_method, $params, $err);
		if(!$err) {
			$_SESSION['accounts'][$account]['balance'] =- TX_SC_EXEC_FEE;
		}
	} else {
		$params = base64_encode(json_encode($params));
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
	$sc_address = $_POST['sc_address'];
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
if(isset($_SESSION['account'])) {
	$address = $_SESSION['account']['address'];
	$balance = $virtual ? $_SESSION['accounts'][$address]['balance'] ?? 0 : api_get("/api.php?q=getBalance&address=$address");
	$public_key = $virtual ? $_SESSION['account']['public_key'] : api_get("/api.php?q=getPublicKey&address=$address");
	$private_key = $_SESSION['account']['private_key'];
}


$smartContract = false;
$sc_address = null;
$compiled_code = null;
$sc_balance = 0;
if(isset($_SESSION['sc_account'])) {
	$sc_address = $_SESSION['sc_account']['address'];
	$sc_balance = $virtual ? $_SESSION['accounts'][$sc_address]['balance'] ?? 0 : api_get( "/api.php?q=getBalence&address=$sc_address");
}

if(isset($_SESSION['contract'])) {
    $smartContract = $_SESSION['contract'];
    $code = $smartContract['code'];
    $compiled_code = $code;
}

$interface = false;
if($virtual) {
	SmartContractEngine::$virtual = true;
	SmartContractEngine::$smartContract = @$_SESSION['contract'];
	if(isset($_SESSION['contract']['interface'])) {
		$interface = $_SESSION['contract']['interface'];
		$interface = json_decode($interface, true);
	}
} else {
	$smartContract = api_get("/api.php?q=getSmartContract&address=$sc_address");
    if($smartContract) {
        $interface = api_get("/api.php?q=getSmartContractInterface&address=$sc_address");
        $interface = json_decode($interface, true);
	    $compiled_code = $smartContract['code'];
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

$folder = SESSION("projectPath");
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
$settings = $Settings->db->select("*");
?>

<form class="" method="post" action="" onsubmit="onSubmit(event)">

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
    <div class="grid align-items-center grid-nogutter">
        <div class="col-3">
            Wallet address:
        </div>
        <div class="col-9">
            <?php if ($virtual) { ?>
                <select name="address" onchange="this.form.submit()">
                    <?php foreach($_SESSION['accounts'] as $account) { ?>
                    <option value="<?php echo $account['address'] ?>"
                            <?php if ($_SESSION['account']['address']==$account['address']) { ?>selected="selected"<?php } ?>><?php echo $account['address'] ?></option>
                    <?php } ?>
                </select>
            <?php } else { ?>
                <input type="text" value="<?php echo $address ?>" class="p-1" name="address"/>
            <?php } ?>
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
    <hr/>
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

    <div class="grid align-items-center grid-nogutter">
        <div class="col-3">Contract address:</div>
        <div class="col-9">
            <div class="flex">
                <?php if($virtual) {?>
                    <select name="sc_address" onchange="this.form.submit()">
		                <?php foreach($_SESSION['accounts'] as $account) { ?>
                            <option value="<?php echo $account['address'] ?>"
                                    <?php if ($account['address']==$sc_address) { ?>selected="selected"<?php } ?>><?php echo $account['address'] ?></option>
		                <?php } ?>
                    </select>
                <?php }else {?>
                    <input type="text" value="<?php echo $sc_address ?>" class="p-1"
                           name="sc_address" <?php if ($smartContract) { ?>readonly="readonly"<?php } ?>>
                    <button type="submit" name="create_new_sc_address" class="p-1">New</button>
                <?php } ?>
            </div>
        </div>
        <div class="col-3">Balance:</div>
        <div class="col-9"><?php echo num($sc_balance) ?></div>
    </div>

	<?php if (!empty($compiled_code)) { ?>
        <button type="submit" name="deploy" class="p-1">Deploy</button>
	<?php } ?>

    <hr/>

	<?php if ($interface) { ?>
        <div class="grid">
            <div class="col text-center"><a href="#" style="color: #fff" onclick="showInterfaceTab('methods'); return false">Methods</a></div>
            <div class="col text-center"><a href="#" style="color: #fff" onclick="showInterfaceTab('views'); return false">Views</a></div>
            <div class="col text-center"><a href="#" style="color: #fff" onclick="showInterfaceTab('properties'); return false">Properties</a></div>
        </div>

        <div style="display: <?php if (!isset($_SESSION['interface_tab']) || $_SESSION['interface_tab']=="methods") { ?>block<?php } else { ?>none<?php } ?>" class="tab" name="methods">
            <div class="grid grid-nogutter">
                <div class="col-3">
                    Amount
                </div>
                <div class="col-9  px-2">
                    <input type="text" value="0" class="p-1" name="amount" placeholder="Amount"/>
                </div>
            </div>
	        <?php if(is_array(@$interface['methods'])) { foreach (@$interface['methods'] as $method) { ?>
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
	        <?php if(is_array(@$interface['views'])) { foreach (@$interface['views'] as $method) {
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
                        <div class="flex-grow-1 px-5">
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
	        <?php if(is_array(@$interface['properties'])) { foreach (@$interface['properties'] as $property) {
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

    <?php } ?>

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
	<input type="hidden" name="signature"/>
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
            signTx(privateKey, 0, <?php echo TX_SC_CREATE_FEE ?>, msg, <?php echo TX_TYPE_SC_CREATE ?>);
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
        let data = amount + '-' + fee + '-' + dst + '-' + msg + '-' + type + '-'
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




