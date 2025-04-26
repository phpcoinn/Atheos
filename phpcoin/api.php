<?php

@define("DEFAULT_CHAIN_ID", "/var/www/phpcoin/chain_id");
define("ROOT", "/var/www/phpcoin");
require_once '/var/www/phpcoin/include/init.inc.php';

if(!isset($_REQUEST["q"])){
    api_err("Missing query");
}

error_reporting(0);

$engines = [
    "virtual"=>[
        "name" => "virtual",
        "title" => "Virtual",
        "node" => "https://node1.phpcoin.net",
        "chainId" => "01"
    ],
    "testnet"=>[
        "name"=>"testnet",
        "title" => "Testnet",
        "node" => "https://node1.phpcoin.net",
        "atheos_url"=>"https://atheos.phpcoin.net",
        "chainId" => "01"
    ],
//    "local"=>[
//        "name"=>"local",
//        "title" => "Local",
//        "node" => "http://phpcoin",
//        "atheos_url"=>"http://phpcoin:90",
//        "chainId" => "01"
//    ],
];

function api_get($url, &$error = null) {
    $res = @file_get_contents($url);
    $res = json_decode($res, true);
    $status = $res['status'];
    if($status == "ok") {
        $data = $res['data'];
        return $data;
    } else {
        $error = $res['data'];
    }
}

function getTxs($address) {
    global $db;
    $sql='select * from (select id, block, height, src, dst, val, fee, signature, type, message, date, public_key, data
               from transactions t where (t.src = ? or t.dst = ?) and t.type in (5,6,7)
                union all 
               (select id, null as block, height, src, dst, val, fee, signature, type, message, date, public_key, data
                from mempool t where (t.src = ? or t.dst = ?) and t.type in (5,6,7))) as txs
                order by txs.height desc';
    $transactions = $db->run($sql,
        [$address,$address,$address,$address],false);
    return $transactions;
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);

$q=$_REQUEST["q"];

session_name(md5(dirname(__DIR__)));
session_start();

set_error_handler(function($no, $str) {
    if(error_reporting() & $no) {
        api_echo("error $no: $str");
    }
});

$virtual = @$_SESSION['engine']['name'] == 'virtual';

if($q == "load") {

    $folder = dirname(__DIR__) . "/workspace/users/" . session_id();
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS));
    $list = array();
    foreach ($rii as $file) {
        if ($file->isDir()){
            continue;
        }
        $list[] = $file->getPathname();
    }
    $files = ["/"];
    foreach($list as $item) {
        if(strpos($item, $folder) !== false) {
            $files[]=str_replace($folder, "", $item);
        }
    }
    SmartContractEngine::$virtual = $virtual;
    if(!$virtual) {
        if(!empty($_SESSION['wallet'])) {
            $_SESSION['wallet']['balance'] = Account::getBalance($_SESSION['wallet']['address']);
        }
        if(!empty($_SESSION['contractWallet'])) {
            $_SESSION['contractWallet']['balance'] = Account::getBalance($_SESSION['contractWallet']['address']);
        }
        $transactions = getTxs($_SESSION['wallet']['address']);
    }
    $res = [
        "engines" => $engines,
        "engine" => $_SESSION['engine'],
        "accounts"=>$_SESSION['accounts'],
        "wallet"=>$_SESSION['wallet'],
        "contractWallet"=>$_SESSION['contractWallet'],
        "contractSources" => $files,
        "contract"=>$_SESSION['contract'] ?? [],
        "transactions"=>$virtual ? $_SESSION["transactions"] ?? [] : $transactions,
        "state"=>SmartContractEngine::getState($_SESSION['contract']['address']),
        "debug_logs"=>$_SESSION['debug_logs'],
        "deployParams"=>$_SESSION["deployParams"] ?? [],
    ];
    api_echo($res);
}
if($q == "changeEngine") {
    $name = $data['name'];
    $_SESSION['engine'] = $engines[$name];
    api_echo(true);
}
if($q == "changeAccount") {
    $address = $data['address'];
    $_SESSION['wallet'] = $_SESSION['accounts'][$address];
    api_echo(true);
}
if($q=="generateAccount") {
    $account = Account::generateAcccount();
    $account['balance']=100;
    $_SESSION['accounts'][$account['address']] = $account;
    $_SESSION['wallet'] = $account;
    api_echo(true);
}
if($q=="reset") {
    $_SESSION['engine']=$engines['virtual'];
    $_SESSION['accounts']=[];
    $_SESSION['wallet']=null;
    for($i=1;$i<=5;$i++) {
        $account = Account::generateAcccount();
        $account['balance']=100;
        $_SESSION['accounts'][$account['address']] = $account;
    }
    $_SESSION['wallet']=$_SESSION['accounts'][0];
    $_SESSION['contractWallet']=null;
    $_SESSION['contract']=null;
    $_SESSION['transactions']=[];
    $_SESSION['debug_logs']=[];
    $_SESSION["deployParams"]=[];
    api_echo(true);
}
if($q == "generateScWallet") {
    $account=Account::generateAcccount();
    $account['balance']=100;
    $_SESSION['accounts'][$account['address']] = $account;
    $_SESSION['contractWallet']=$account;
    api_echo(true);
}
if($q == "compile") {
    $address = $data['address'];
    $source = $data['source'];
    $_SESSION['contract']['source'] = $source;
    $_SESSION['contract']['address'] = $address;
    SmartContractEngine::$virtual = $virtual;
    $phar_file = "/var/www/phpcoin/tmp/sc/".$address.".phar";
    $folder = dirname(__DIR__) . "/workspace/users/" . session_id();
    if($source == "/") {
        $file =$folder."/index.php";
    } else {
        $file = $folder.$source;
    }
    $res = SmartContract::compile($address,$file, $phar_file, $err);
    if(!$res) {
        api_err("Error compiling contract: $err");
    }
    $code=base64_encode(file_get_contents($phar_file));
    $interface = SmartContractEngine::verifyCode($code, $error, $address);
    if(!$interface) {
        api_err("Error verify smart contract: ".$error);
    }
    $_SESSION['contract']['phar_code']=$code;
    $_SESSION['contract']['interface']=$interface;
    $_SESSION['contract']['status']='compiled';
    api_echo($_SESSION['contract']);
}
if($q=="connectScWallet") {
    $address = $data['address'];
    if(empty($address)) {
        $address = $_SESSION['contract']['address'];
    }
    if(empty($address)) {
        api_err("Empty address");
    }
    if(!Account::valid($address)) {
        api_err("Invalid address");
    }
    $smartContract = SmartContract::getById($address);
    if(!$smartContract) {
        api_err("Not found smart contract fro address '$address'");
    }
    $codeData=base64_decode($smartContract['code']);
    $codeData=json_decode($codeData,true);
    $interface = SmartContractEngine::getInterface($address);
    if(!$interface) {
        api_err("Error verify smart contract: ".$error);
    }
    $name=$smartContract['name'];
    $description=$smartContract['description'];
    $metadata_str=$smartContract['metadata'];
    $tx=Transaction::getSmartContractCreateTransaction($address);
    $data = $tx['data'];
    $data = base64_decode($data);
    $data = json_decode($data,true);
    $deployParams = [];
    foreach($interface['deploy']['params'] as $ix => $param) {
        $deployParams[$param['name']] = $data['params'][$ix];
    }
    $_SESSION['contract']['address']=$address;
    $_SESSION['contract']['phar_code']=$codeData['code'];
    $_SESSION['contract']['interface']=$interface;
    $_SESSION['contract']['name'] = $name;
    $_SESSION['contract']['description'] = $description;
    $_SESSION['contract']['metadata'] = $metadata_str;
    $_SESSION['contract']['status']='deployed';
    $_SESSION['contract']['signature']=$smartContract['signature'];
    $_SESSION['contract']['params']=$data['params'];
    $_SESSION['contract']["deployParams"]=$deployParams;
    $_SESSION['contract']["amount"]=$data['amount'];
    $_SESSION["deployParams"]=$deployParams;
    $_SESSION['contractWallet']['address']=$address;
    api_echo($_SESSION['contract']);
}
if($q=="getSource") {
    $address=$_SESSION['contract']['address'];
    if($virtual) {
        $code=$_SESSION['contract']['phar_code'];
        $code=base64_decode($code);
    } else {
        $engine = $_SESSION['engine'];
        $node = $engine['node'];
        $smartContract = api_get($node . "/api.php?q=getSmartContract&address=".$address);
        $code=$smartContract['code'];
        $decoded=json_decode(base64_decode($code), true);
        $code = base64_decode($decoded['code']);
    }

    $phar_file = "/var/www/phpcoin/tmp/sc/".$address.".phar";
    file_put_contents($phar_file, $code);

    ob_end_clean();
    header("Content-Description: File Transfer");
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"". basename($phar_file) ."\"");

    readfile ($phar_file);
    exit();
}
if($q == "sourceToEditor") {
    $address=$_SESSION['contract']['address'];
    $phar_file = "/var/www/phpcoin/tmp/sc/".$address.".phar";
    $smartContract=SmartContract::getById($address);
    $code=$smartContract['code'];
    $decoded=json_decode(base64_decode($code), true);
    $code = base64_decode($decoded['code']);
    file_put_contents($phar_file, $code);
    try {
        $phar = new Phar($phar_file);
    } catch (Throwable $t) {
        api_echo($t->getMessage());
    }
    $folder = dirname(__DIR__) . "/workspace/users/" . session_id()."/".$address;
    mkdir($folder);
    $phar->extractTo($folder);
    api_echo($phar_file);
}
if($q=="signDeploy") {
    $contactData=$data['contract'];
    $name=$contactData['name'];
    $description=$contactData['description'];
    $metadata_str=$contactData['metadata'];
    $deployParams = $data['deployParams'];
    $_SESSION['deployParams']=$deployParams;

    $engine = $_SESSION['engine'];
    $node = $engine['node'];

    SmartContractEngine::$virtual = $virtual;
    $address = $_SESSION['wallet']['address'];
    if(empty($address)) {
        api_err('Wallet address is required');
    }
    $deploy_address = $_SESSION['contract']['address'];
    if(empty($deploy_address)) {
        api_err('Contract address is required');
    }
    if($virtual && $deploy_address == $address) {
        api_err('Invalid contract address');
    }
    if(!Account::valid($deploy_address)) {
        api_err('Contract address is not valid');
    }
    if(!$virtual) {
        $smartContract = api_get($node . "/api.php?q=getSmartContract&address=$deploy_address");
        if($smartContract) {
            api_err('Smart contract with address already exists');
        }
    }
    $contract = $_SESSION['contract'];

    $compiled_code = $contract['phar_code'];
    $amount=$contract['amount'];
    if(empty($amount)) $amount=0;

    $interface = SmartContractEngine::verifyCode($compiled_code, $error, $deploy_address);
    $metadata=json_decode($metadata_str, true);
    $metadata['name']=$name;
    $metadata['description']=$description;
    $deployParams=array_values($deployParams);
    $data = [
        "code"=>$compiled_code,
        "amount"=>num($amount),
        "params"=>$deployParams,
        "metadata"=>$metadata,
        "interface"=>$interface
    ];
    $txdata = base64_encode(json_encode($data));
    $_SESSION['contract']['name'] = $contactData['name'];
    $_SESSION['contract']['description'] = $contactData['description'];
    $_SESSION['contract']['metadata'] = $contactData['metadata'];
    $_SESSION['contract']['amount'] = $contactData['amount'];
    $_SESSION['contract']['code']=$txdata;
    $_SESSION['contract']['txdata']=$txdata;
    api_echo($_SESSION['contract']);
}
if($q === "deploy") {
    $signature = $data['signature'];
    $wallet = $_SESSION['wallet'];
    $public_key = $wallet['public_key'];
    $date = time();
    $contract=$_SESSION['contract'];
    $txdata = $contract['txdata'];
    $deploy_address = $contract['address'];
    $deploy_amount = $contract['amount'];

    $msg = $signature;
    $transaction = new Transaction($public_key,$deploy_address,$deploy_amount,TX_TYPE_SC_CREATE,$date, $msg, TX_SC_CREATE_FEE);
    $transaction->data = $txdata;
    if($virtual) {
        SmartContractEngine::$virtual = true;
        SmartContractEngine::$smartContracts[$deploy_address] = $contract;
        //SmartContractEngine::cleanVirtualState($deploy_address);
        $res = SmartContractEngine::process($deploy_address, [$transaction], 0, false, $err);
        $debug_logs = SmartContractEngine::$debug_logs ?? [];
        @$_SESSION['debug_logs']=array_merge($_SESSION['debug_logs'] ?? [], $debug_logs);
        if(!$res) {
            api_err('Smart contract not deployed: '.$err);
        } else {
            $_SESSION['transactions'][]=$transaction->toArray();
            $_SESSION['accounts'][$address]['balance']=$_SESSION['accounts'][$address]['balance'] - TX_SC_CREATE_FEE;
            $_SESSION['accounts'][$address]['balance'] = round($_SESSION['accounts'][$address]['balance'],8);
            $_SESSION['accounts'][$deploy_address]['balance']+=$deploy_amount;
            $_SESSION['accounts'][$deploy_address]['balance']=round($_SESSION['accounts'][$deploy_address]['balance'],8);
        }
        $_SESSION['contract']['height']=1;
        $_SESSION['contract']['signature']=$signature;
        $_SESSION['contract']['status']="deployed";
        api_echo(true);
    }
}
if($q === "callMethod") {
    $address = $data['address'];
    $fn_address = $data['fn_address'];
    $amount = $data['amount'];
    $method_type=$data['method_type'];
    $exec_method = $data['exec_method'];
    $exec_params = $data['exec_params'];
    if(strlen($amount)==0) $amount=0;
    $engine = $_SESSION['engine'];
    $node = $engine['node'];
    if(empty($method_type)) {
        api_err('Method type not selected');
    }
    if($method_type == "exec") {
        if(!Account::valid($fn_address)) {
            api_err("Contract address is invalid");
        }
        $type=TX_TYPE_SC_EXEC;
        $src=$address;
        $dst=$fn_address;
    } else if ($method_type == "send") {
        if(!$virtual) {
            $smartContract = api_get($node . "/api.php?q=getSmartContract&address=".$_SESSION['wallet']['address']);
            if(!$smartContract) {
                api_err('Connect smart contract wallet for call send method');
            }
        }
        $type=TX_TYPE_SC_SEND;
        $src=$address;
        $dst=$fn_address;
        if(empty($dst)) {
            api_err('Destination address must be specified');
        }
        if(!Account::valid($dst)) {
            api_err("Address is invalid");
        }
        if($virtual && $dst == $src) {
            api_err('Invalid destination address');
        }
    }
    $exec_params=array_values($exec_params);
    $callData = [
        "method" => $exec_method,
        "params" => $exec_params
    ];
    $msg=base64_encode(json_encode($callData));
    $wallet = $_SESSION['wallet'];

    if($virtual) {
        $date = time();
        $public_key = $wallet['public_key'];
        $transaction = new Transaction($public_key,$dst,$amount,$type,$date, $msg, TX_SC_EXEC_FEE);
        $hash = $transaction->hash();
        SmartContractEngine::$virtual = true;
        SmartContractEngine::$smartContracts[$_SESSION['contract']['address']] = $_SESSION['contract'];
        $res = SmartContractEngine::process($_SESSION['contract']['address'], [$transaction], count($_SESSION['transactions']), false, $err);
        if(!$res) {
            api_err('Method can not be executed: '.$err);
        }
        $debug_logs = SmartContractEngine::$debug_logs;
        $_SESSION['debug_logs']=array_merge($_SESSION['debug_logs'], $debug_logs);
        $_SESSION['accounts'][$src]['balance'] = $_SESSION['accounts'][$src]['balance'] - (TX_SC_EXEC_FEE + $amount);
        $_SESSION['accounts'][$dst]['balance'] = $_SESSION['accounts'][$dst]['balance'] + $amount;
        $_SESSION['accounts'][$src]['balance'] = round($_SESSION['accounts'][$src]['balance'], 8);
        $_SESSION['accounts'][$dst]['balance'] = round($_SESSION['accounts'][$dst]['balance'], 8);
        $_SESSION['transactions'][]=$transaction->toArray();
        api_echo(true);
    } else {
        $tx = [
            "src"=>$wallet['address'],
            "dst"=>$dst,
            "val"=>$amount,
            "msg"=>$msg,
            "fee"=>TX_SC_EXEC_FEE,
            "type" => $type,
            "date"=>time()
        ];
        $tx = base64_encode(json_encode($tx));
        $redirect = urlencode($engine['atheos_url'].'?');
        $request_code = uniqid("signtx");
        $_SESSION['request_code'] = $request_code;
        $url=$engine['node'] . "/dapps.php?url=".MAIN_DAPPS_ID."/gateway/approve.php?&app=Atheos&request_code=$request_code&redirect=$redirect&tx=$tx";
        api_echo($url);
    }
}
if($q == "callView") {
    $sc_address = $_SESSION['contract']['address'];
    $view_method = $data['method'];

    $params = $data['view_params'];
    $engine = $_SESSION['engine'];
    $node = $engine['node'];
    if($virtual) {
        SmartContractEngine::$virtual = true;
        SmartContractEngine::$smartContracts[$sc_address] = $_SESSION['contract'];
        $res = SmartContractEngine::view($sc_address, $view_method, $params, $err);
        $debug_logs = SmartContractEngine::$debug_logs;
        $_SESSION['debug_logs']['view::'.$view_method]=$debug_logs;
    } else {
        $params = array_values($params);
        $params = base64_encode(json_encode($params));
        $res = api_get($node."/api.php?q=getSmartContractView&address=$sc_address&method=$view_method&params=$params");
    }
    if($err) {
        api_err('View can not be executed: '.$err);
    }
    api_echo($res);
}
if($q == "getProperty") {
    $property = $data['name'];
    $key = $data['key'];
    if(strlen($key)==0) {
        $key = null;
    }
    $sc_address = $_SESSION['contract']['address'];
    $engine = $_SESSION['engine'];
    $node = $engine['node'];
    if($virtual) {
        SmartContractEngine::$virtual = true;
        SmartContractEngine::$smartContracts[$sc_address] = $_SESSION['contract'];
        $val = SmartContractEngine::get($sc_address, $property, $key, $err);
    } else {
        $val = api_get($node."/api.php?q=getSmartContractProperty&address=$sc_address&property=$property&key=$key", $error);
        if($error) {
            $_SESSION['get_property_val'][$property]['error']=$error;
        }
    }
    api_echo($val);
}
if($q == "loginWallet") {
    $request_code = uniqid();
    $_SESSION['auth_request_code']=$request_code;
    $engine = $_SESSION['engine'];
    $redirect = urlencode($engine['atheos_url']."/phpcoin/api.php?q=afterLoginWallet");
    $url=$engine['node']."/dapps.php?url=".MAIN_DAPPS_ID."/wallet/auth.php?app=Athoes&request_code=$request_code&store_private_key=1&redirect=$redirect";
    api_echo($url);
}
if($q == "afterLoginWallet") {
    $engine = $_SESSION['engine'];
    if(isset($_REQUEST['auth_data'])) {
        $auth_data = json_decode(base64_decode($_GET['auth_data']), true);
        if($_SESSION['auth_request_code'] == $auth_data['request_code']) {
            $address=$auth_data['account']['address'];
            $_SESSION['wallet']['address']=$address;
        }
    }
    header('Location:'.$engine['atheos_url']);
    exit;
}
if($q=="logoutWallet") {
    unset($_SESSION['wallet']);
    api_echo(true);
}
if($q == "loginScWallet") {
    $request_code = uniqid();
    $_SESSION['auth_request_code']=$request_code;
    $engine = $_SESSION['engine'];
    $redirect = urlencode($engine['atheos_url']."/phpcoin/api.php?q=afterLoginScWallet");
    $url=$engine['node']."/dapps.php?url=".MAIN_DAPPS_ID."/wallet/auth.php?app=Athoes&request_code=$request_code&redirect=$redirect";
    api_echo($url);
}
if($q == "afterLoginScWallet") {
    $engine = $_SESSION['engine'];
    if(isset($_REQUEST['auth_data'])) {
        $auth_data = json_decode(base64_decode($_GET['auth_data']), true);
        if($_SESSION['auth_request_code'] == $auth_data['request_code']) {
            $address=$auth_data['account']['address'];
            $_SESSION['contractWallet']['address']=$address;
            $_SESSION['contract']['address']=$address;
        }
    }
    header('Location:'.$engine['atheos_url']);
    exit;
}
if($q == "logoutScWallet") {
    unset($_SESSION['contractWallet']);
    api_echo(true);
}
if($q=="deployReal") {
    $engine = $_SESSION['engine'];
    if(empty($_REQUEST['signature_data'])) {
        //go back
        header("Location:".$engine['atheos_url']);
        exit;
    }
    $request_code = uniqid("signtx");
    $redirect = urlencode($engine['atheos_url']."/phpcoin/api.php?q=afterDeployReal");
    $signature_data = base64_decode($_REQUEST['signature_data']);
    $signature_data = json_decode($signature_data, true);
    $signature = $signature_data['signature'];
    $_SESSION['contract']['signature']=$signature;
    $tx = [
        "src"=>$_SESSION['wallet']['address'],
        "dst"=>$_SESSION['contractWallet']['address'],
        "val"=>$_SESSION['contract']['amount'],
        "msg"=>$signature,
        "fee"=>TX_SC_CREATE_FEE,
        "type" => TX_TYPE_SC_CREATE,
        "date"=>time()
    ];
    $tx = base64_encode(json_encode($tx));
    $url=$engine['node'] . "/dapps.php?url=".MAIN_DAPPS_ID."/gateway/approve.php?&app=Atheos&request_code=$request_code&redirect=$redirect&tx=$tx";
    $txdata = $_SESSION['contract']['txdata'];
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<title>Redirecting...</title>';
    echo '</head>';
    echo '<body>';
    echo '<form id="redirectForm" action="' . $url . '" method="POST">';
    echo '<input type="hidden" name="txdata" value="' . htmlentities($txdata) . '">';
    echo '</form>';
    echo '<script>';
    echo 'document.getElementById("redirectForm").submit();'; // Automatically submit the form
    echo '</script>';
    echo '</body>';
    echo '</html>';
    exit;
}
if($q=="afterDeployReal") {
    $engine = $_SESSION['engine'];
    if(empty($_REQUEST['res'])) {
        //go back
        header("Location:".$engine['atheos_url']);
        exit;
    }
    $_SESSION['contract']['status']='deployed';
    header('Location:'.$engine['atheos_url']);
    exit;
}
if($q=="reloadTxs") {
    if($virtual) {
        $transactions = $_SESSION["transactions"];
    } else {
        global $db;
        $transactions = getTxs($_SESSION['wallet']['address']);
    }
    $data['transactions']=$transactions;
    $data['state']=SmartContractEngine::getState($_SESSION['contract']['address']);
    $data['debug_logs']=$_SESSION['debug_logs'];
    api_echo($data);
}
if($q == "clearState") {
    if($virtual) {
        SmartContractEngine::cleanVirtualState($_SESSION['contract']['address']);
    }
    api_echo(true);
}
if($q == "clearLog") {
    if($virtual) {
        $_SESSION['debug_logs']=[];
    }
    api_echo(true);
}
api_err("Invalid query");

