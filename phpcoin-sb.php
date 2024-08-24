<?php
//PHPCoin - SC sidebar

require_once("common.php");


error_reporting(0);
require_once '/var/www/phpcoin/include/init.inc.php';

require_once __DIR__ ."/phpcoin/actions.php";

if(isset($_POST['ajax'])) {
    $ajax = true;
    $formData = $_POST['formData'];
    parse_str($formData, $_POST);
    ob_end_clean();
    ob_start();
    register_shutdown_function(function () {
        $content = ob_get_contents();
        ob_end_clean();
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
	"testnet" => [
		"name" => "Testnet",
		"NODE_URL" => "https://node1.phpcoin.net",
        "atheos_url"=>"https://atheos.phpcoin.net"
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

function api_get($url, &$error = null) {
	$res = @file_get_contents(NODE_URL . $url);
	$res = json_decode($res, true);
	$status = $res['status'];
	if($status == "ok") {
		$data = $res['data'];
		return $data;
	} else {
        $error = $res['data'];
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

function get_params_placeholder($params) {
    $placeholder_arr=[];
    foreach($params as $param) {
        $name=$param['name'];
        if(!$param['required']) {
            $value=$param['value'];
            $name="[$name=$value]";
        }
        $placeholder_arr[]=$name;
    }
    return implode(" ", $placeholder_arr);
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
        if(isset($_GET['login_contract'])) {
            $address=$auth_data['account']['address'];
            $smartContract = api_get("/api.php?q=getSmartContract&address=".$address);
            $_SESSION['contract']=$smartContract;
        } else if (isset($_GET['login_new_contract'])) {
            $_SESSION['deploy_address']=$auth_data['account']['address'];
        } else if (isset($_GET['select_address'])) {
            $_SESSION['fn_address']=$auth_data['account']['address'];
        } else {
		    $_SESSION['account']=$auth_data['account'];
        }
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

if(isset($_GET['get_source'])) get_source();
if(isset($_POST['reset'])) resetForm();
if(isset($_POST['compile'])) compile();
//if(isset($_POST['sign_contract'])) sign_contract($virtual);
if(isset($_POST['deploy'])) deploy($virtual);
if(isset($_POST['save'])) save();
if(isset($_POST['set_contract'])) set_contract();
if(isset($_POST['login_contract'])) login_contract();
if(isset($_POST['load_contract'])) load_contract();
if(isset($_POST['login_new_contract'])) login_new_contract();
if(isset($_POST['exec_method'])) exec_method($virtual);
if(isset($_POST['view_method'])) view_method($virtual);
if(isset($_POST['get_property_val'])) get_property_val($virtual);
if(isset($_POST['clear_property_val'])) clear_property_val();
if(isset($_POST['clear_view_method_val'])) clear_view_method_val();
if(isset($_POST['wallet_auth'])) wallet_auth();
if(isset($_POST['logout'])) logout();
if(isset($_GET['download'])) download($virtual);
if(isset($_POST['select_address'])) select_address();
if(isset($_POST['clear_debug_log'])) clear_debug_log();



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
    if(!isset($_SESSION['contract'])) {
	    $_SESSION['contract'] = api_get( "/api.php?q=generateAccount");
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
        $smartContract = api_get("/api.php?q=getSmartContract&address=".$address);
        if($smartContract) {
            $_SESSION['contract']=$smartContract;
            $_SESSION['contract']['status']="deployed";
        } else {
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
        $interface = api_get("/api.php?q=getSmartContractInterface&address=".$_SESSION['contract']['address'].XDEBUG);
        if($interface) {
            $_SESSION['contract']['interface']=$interface;
        }
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


//require_once __DIR__ ."/phpcoin/app.php";


?>

<form id="phpcoin-sb" class="p-2" method="post" action="" onsubmit="onSubmit(event)">

    <div class="flex flex-wrap align-items-center align-content-between">
        <div class="">
            <h4>Smart Contract actions</h4>
        </div>
        <div class="ml-auto">
            <button name="save" type="submit" class="p-1">Save</button>
            <button name="reset" type="submit" class="p-1">Reset</button>
            <button name="refresh" type="submit" class="p-1">Refresh</button>
        </div>
    </div>
    <hr/>
    <div class="grid align-items-center grid-nogutter">
        <div class="col-12 sm:col-3">Engine:</div>
        <div class="col-12 sm:col-9">
            <select name="engine" class="p-1" onchange="$('[name=save]').click()">
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
                    <div>
                    <a target="_blank" href="<?php echo NODE_URL ?>/apps/explorer/address.php?address=<?php echo $_SESSION['account']['address'] ?>">
                        <?php echo $_SESSION['account']['address'] ?>
                    </a>
                    </div>
                        <?php if (!empty($_SESSION['account']['account_name'])) { ?>
                        <div>(<?php echo $_SESSION['account']['account_name'] ?>)</div>
                        <?php } ?>
                    <input type="hidden" name="address" value="<?php echo $_SESSION['account']['address'] ?>"/>
                    <div>
                        Balance: <?php echo $balance ?>
                    </div>
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
                    <select name="contract" class="p-1">
                        <option value="">Select...</option>
                        <?php foreach($deployedContracts as $contract) { ?>
                            <option value="<?php echo $contract['address'] ?>" <?php if ($contract['address']==@$_SESSION['contract']['address']) { ?>selected<?php } ?>>
                                <?php echo $contract['address'] ?>
                                <?php if (!empty($contract['name'])) echo " (" . $contract['name'] .")" ?>
                            </option>
                        <?php } ?>
                    </select>
                    <button name="set_contract" class="p-1">Set contract</button>
<!--                    <button name="login_contract" class="p-1">Select contract</button>-->
                </div>
            </div>
            <?php if ($_SESSION['contract']['status']=="deployed") { ?>
                <div class="col-12 sm:col-3">Height:</div>
                <div class="col-12 sm:col-9"><?php echo $_SESSION['contract']['height'] ?></div>
                <div class="col-12 sm:col-3">
                    Code:<br/>
                    <a href="/phpcoin-sb.php?download" target="_blank">
                        <button type="button" name="download" class="p-1">Download</button>
                    </a>
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

    <?php if (!$virtual && false) { ?>
        <div class="grid align-items-center grid-nogutter">
            <div class="col-12 sm:col-3">Contract:</div>
            <div class="col-12 sm:col-7">
                <input name="load_contract_address" type="text" value="<?php echo @$_SESSION['contract']['address'] ?>"/>
            </div>
            <div class="col-12 sm:col-2">
                <button name="load_contract">Load contract</button>
            </div>
        </div>
    <?php } ?>

    <?php if(@$_SESSION['contract']['status']!="deployed" || true) { ?>
        <hr/>
        Contract source
        <div class="grid align-items-center grid-nogutter">
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
                            <button type="submit" name="login_new_contract">Login</button>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>


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
                <div class="col-12 sm:col-3">Metadata</div>
                <div class="col-12 sm:col-9">
                    <input type="text" name="contract_metadata" value="<?php echo $_SESSION['contract']['metadata'] ?>" placeholder="Metadata (JSON)"
                           <?php if($disabled) { ?>readonly<?php } ?>/>
                </div>
                <div class="col-12 sm:col-3">Amount</div>
                <div class="col-12 sm:col-9">
                    <input type="text" name="deploy_amount" value="<?php echo $_SESSION['contract']['deploy_amount'] ?>" placeholder="0"
                           <?php if($disabled) { ?>readonly<?php } ?>/>
                </div>
                <div class="col-12 sm:col-3">Parameters</div>
                <div class="col-12 sm:col-9">
                    <input type="text" name="deploy_params" value="<?php echo htmlspecialchars($_SESSION['contract']['deploy_params']) ?>"
                           placeholder="<?php echo get_params_placeholder(@$_SESSION['contract']['interface']['deploy']['params']) ?>"
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
        <a class="block" target="_blank" href="<?php echo NODE_URL ?>/apps/explorer/smart_contract.php?id=<?php echo $_SESSION['contract']['address'] ?>">
            <?php echo $_SESSION['contract']['address'] ?>
        </a>
        <br/>Name: <?php echo  $_SESSION['contract']['name'] ?>
        <br/>Description: <?php echo  $_SESSION['contract']['description'] ?>
        <br/>Metadata: <?php echo  $_SESSION['contract']['metadata'] ?>
        <br/>Balance: <?php echo $_SESSION['contract']['balance'] ?>
            </div>
        <div class="grid if-tabs">
            <div class="col text-center if-tab <?php if(!isset($_SESSION['interface_tab']) || @$_SESSION['interface_tab']=="methods") { ?>sel-tab<?php } ?>">
                <a href="#" onclick="showInterfaceTab(this,'methods'); return false">Methods</a></div>
            <div class="col text-center if-tab <?php if(@$_SESSION['interface_tab']=="views") { ?>sel-tab<?php } ?>">
                <a href="#" onclick="showInterfaceTab(this,'views'); return false">Views</a></div>
            <div class="col text-center if-tab <?php if(@$_SESSION['interface_tab']=="properties") { ?>sel-tab<?php } ?>">
                <a href="#" onclick="showInterfaceTab(this,'properties'); return false">Properties</a></div>
        </div>

        <div style="display: <?php if (!isset($_SESSION['interface_tab']) || $_SESSION['interface_tab']=="methods") { ?>block<?php } else { ?>none<?php } ?>" class="tab" name="methods">
            <div class="grid grid-nogutter row">
                <div class="col-12 sm:col-3">
                    Type
                </div>
                <div class="col-12 sm:col-9 px-2">
                    <input onclick="onSubmit(event)" type="radio" name="method_type" id="method_type_exec" value="exec"
                           style="width: auto" <?php if (!isset($_SESSION['method_type']) || @$_SESSION['method_type']=="exec") { ?>checked<?php } ?>
                           /> Exec
                    <input onclick="onSubmit(event)" type="radio" name="method_type" id="method_type_send" value="send"
                           style="width: auto" <?php if (@$_SESSION['method_type']=="send") { ?>checked<?php } ?>
                    <?php if (@$_SESSION['account']['address']!=@$_SESSION['contract']['address']) { ?>disabled="disabled"<?php } ?>/> Send
                </div>
                <div class="col-12 sm:col-3">
                    Address
                </div>
                <div class="col-12 sm:col-9 px-2 flex flex-wrap">
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
                        <input type="text" value="<?php echo @$_SESSION['fn_address'] ?>" class="p-1" style="flex: 1" name="fn_address" placeholder="<?php echo $_SESSION['contract']['address'] ?>"/>
                        <?php if (@$_SESSION['method_type']=="send") { ?>
                            <button type="submit" name="select_address">Select</button>
                        <?php } ?>
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
                <div class="col-12 sm:col-9 px-2">
                    <input type="text" value="<?php echo @$_SESSION['amount'] ?>" class="p-1" name="amount" placeholder="Amount"/>
                </div>
            </div>
	        <?php if(is_array($_SESSION['contract']['interface']['methods'])) {
                foreach ($_SESSION['contract']['interface']['methods'] as $method) { ?>
                <div class="grid grid-nogutter p-2 border-1 m-2">
                    <div class="col-12 sm:col-3">
                        <button type="submit" class="p-1 w-full" data-call="<?php echo $method['name'] ?>" name="exec_method[<?php echo $method['name'] ?>]"><?php echo $method['name'] ?></button>
                    </div>
                    <div class="col-12 sm:col-9  px-2">
                        <?php if (count($method['params']) > 0) {
                            ?>
                            <input type="text" value="<?php echo htmlspecialchars(@$_SESSION['params'][$method['name']]) ?>" class="p-1" name="params[<?php echo $method['name'] ?>]"
                                   placeholder="<?php echo get_params_placeholder($method['params']) ?>"/>
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
                <div class="grid grid-nogutter p-2 m-2 border-1">
                    <div class="col-12 sm:col-3">
                        <button type="submit" class="p-1 w-full" name="view_method[<?php echo $name ?>]"><?php echo $name ?></button>
                    </div>
                    <div class="col-12 sm:col-9 flex align-items-center align-content-between  px-2">
                        <?php if (count($method['params']) > 0) { ?>
                            <input type="text" class="flex-grow-1 p-1" value="<?php echo @$_SESSION['params'][$name] ?>" name="params[<?php echo $name ?>]"
                                   placeholder="<?php echo get_params_placeholder($method['params']) ?>"/>
                        <?php } ?>
                        <div class="flex-grow-1 px-5 white-space-normal word-break">
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
                <div class="grid grid-nogutter p-2 m-2 border-1">
                    <div class="col-12 sm:col-3">
                        <button type="submit" class="p-1 w-full" name="get_property_val[<?php echo $name ?>]"><?php echo $name ?></button>
                    </div>
                    <div class="col-12 sm:col-9 flex flex-wrap align-items-center align-content-between px-2">
                        <?php if($type == "map") { ?>
                            <input type="text" class="flex-grow-1 p-1" name="property_key[<?php echo $name ?>]" value="<?php echo @$_SESSION['property_key'][$name] ?>"
                                   placeholder="Key"/>
                        <?php } ?>
                        <div class="flex-grow-1 px-5">
                            <span class="word-break"><?php echo @$_SESSION['get_property_val'][$name]['value'] ?></span>
                            <?php if (isset($_SESSION['get_property_val'][$name]['error'])) { ?>
                                <i class="fa fa-exclamation-triangle" style="color: red" title="<?php echo @$_SESSION['get_property_val'][$name]['error'] ?>"></i>
                            <?php } ?>
                        </div>
                        <?php if (isset($_SESSION['get_property_val'][$name]['return'])) { ?>
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
            <?php foreach($transactions as $ix=>$tx) {
                if(empty($tx['block'])) {
                    $type = $tx['type_value'];
                } else {
                    $type = $tx['type'];
                }
                if(!in_array($type,[TX_TYPE_SC_CREATE, TX_TYPE_SC_EXEC, TX_TYPE_SC_SEND]) && !$virtual) continue;
                if($type==TX_TYPE_SC_CREATE && $tx['dst']!=$_SESSION['contract']['address']) continue;
                if($type==TX_TYPE_SC_EXEC && $tx['dst']!=$_SESSION['contract']['address']) continue;
                if($type==TX_TYPE_SC_SEND && $tx['src']!=$_SESSION['contract']['address']) continue;
                ?>
                <tr>
                    <td><?php echo $virtual ? $ix : (empty($tx['block']) ? "<a href=\"".NODE_URL .'/apps/explorer/tx.php?id='.$tx['id']."\" target=\"_blank\">mempool</a>" : '<a href="'. NODE_URL .'/apps/explorer/tx.php?id='.$tx['id'].'" target="_blank">'.$tx['height'].'</a>') ?></td>
                    <td><a href="<?php echo NODE_URL ?>/apps/explorer/address.php?address=<?php echo @$tx['src'] ?>" target="_blank"><?php echo @$tx['src'] ?></a></td>
                    <td><a href="<?php echo NODE_URL ?>/apps/explorer/address.php?address=<?php echo @$tx['dst'] ?>" target="_blank"><?php echo $tx['dst'] ?></a></td>
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
                        if($type==TX_TYPE_SC_CREATE) {
                            echo "deploy";
                        }
                        if($type==TX_TYPE_SC_EXEC || $type==TX_TYPE_SC_SEND) {
                            $data = json_decode(base64_decode($tx['message']),true);
                            echo $data['method'];
                        }
                        ?>
                    </td>
                    <td><?php
                        if($type==TX_TYPE_SC_CREATE) {
                            $data= json_decode(base64_decode($tx['data']), true);
                            echo json_encode($data['params']);
                        }
                        if($type==TX_TYPE_SC_EXEC || $type==TX_TYPE_SC_SEND) {
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
    <?php if ($virtual) { ?>
        <div class="mt-3">
            Debug: <button name="clear_debug_log" type="submit" class="p-1">Clear</button>
            <pre><?php print_r(@$_SESSION['debug_logs']); ?></pre>
        </div>
    <?php } ?>
	<input type="hidden" name="msg"/>
	<input type="hidden" name="date"/>
</form>




<link rel="stylesheet" href="https://unpkg.com/primeflex@3.1.2/primeflex.css">

<script src="<?php echo NODE_URL ?>/apps/common/js/jquery.min.js"></script>
<script src="<?php echo NODE_URL ?>/apps/common/js/phpcoin-crypto.js" type="text/javascript"></script>
<script src="/phpcoin/scripts.js" type="text/javascript"></script>
<script type="text/javascript">



    <?php if (!$virtual && isset($_GET['deploy'])) {
        $d=1;
        ?>
    $(function() {
        $("[name=deploy]").click();
    });
    <?php } ?>


</script>

<link rel="stylesheet" href="phpcoin/style.css"/>
<style>
    #SBRIGHT {
        width: <?php echo @$settings['sidebars.sb-right-width'] ?>;
    }
    #SBLEFT {
        width: <?php echo @$settings['sidebars.sb-left-width'] ?>;
    }
    #SBRIGHT:not(.drag) {
        user-select: initial !important;
    }
    .word-break{
        word-break: break-word;
        white-space: break-spaces;
    }
</style>


