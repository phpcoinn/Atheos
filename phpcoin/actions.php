<?php

//error_reporting(0);
//ini_set('display_errors', 0);

function _error($text) {
    $response = [
        "action" => "message",
        "message" => ['icon'=>'error', 'text'=> $text]
    ];
    echo "json:".json_encode($response);
    exit;
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
    unset($_SESSION['debug_logs']);
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
//    unset($_SESSION['contract']);

    $_SESSION['contract']['phar_code']=null;
    $res = SmartContract::compile($_SESSION['contract']['address'],$file, $phar_file, $err);
    if(!$res) {
        _error('Error compiling smart contract:'.$err);
    }
    $code=base64_encode(file_get_contents($phar_file));
    $interface = SmartContractEngine::verifyCode($code, $error);
    if(!$interface) {
        _error("Error verify smart contract:\n".$error);

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
        _error('Wallet address is required');
    }
    $deploy_address = $_POST['deploy_address'];
    if(empty($deploy_address)) {
        _error('Contract address is required');
    }

    if($virtual && $deploy_address == $address) {
        _error('Invalid contract address');
    }

    if(!Account::valid($deploy_address)) {
        _error('Contract address is not valid');
    }

    $_SESSION['deploy_address']=$deploy_address;

    $smartContract = api_get("/api.php?q=getSmartContract&address=$deploy_address");
    if($smartContract && !$virtual) {
        _error('Smart contract with address already exists');
    }

    $public_key = $_SESSION['account']['public_key'];

    $date = $_POST['date'];
    $compiled_code = $_POST['compiled_code'];
    $amount=$_POST['deploy_amount'];
    if(empty($amount)) $amount=0;

    $_SESSION['contract']['deploy_amount']=$amount;

    $name=$_POST['contract_name'];
    $description=$_POST['contract_description'];
    $metadata_str=$_POST['contract_metadata'];


    $_SESSION['deploy_params']=$_POST['deploy_params'];
    $post_deploy_params = $_POST['deploy_params'];

    $deploy_params = SmartContractEngine::parseCmdLineArgs($post_deploy_params);

    $interface = SmartContractEngine::verifyCode($compiled_code, $error);

    $metadata=json_decode($metadata_str, true);
    $metadata['name']=$name;
    $metadata['description']=$description;

    $data = [
        "code"=>$compiled_code,
        "amount"=>num($amount),
        "params"=>$deploy_params,
        "metadata"=>$metadata,
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
        unset($_SESSION['transactions']);
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
        SmartContractEngine::cleanVirtualState($deploy_address);
        $res = SmartContractEngine::process($deploy_address, [$transaction], 0, false, $err);
        $debug_logs = SmartContractEngine::$debug_logs ?? [];
        @$_SESSION['debug_logs']=array_merge($_SESSION['debug_logs'] ?? [], $debug_logs);

        if(!$res) {
            _error('Smart contract not deployed: '.$err);
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
    unset($_SESSION['get_property_val']);
    unset($_SESSION['get_property_val']);
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

function load_contract(){
    $address=$_POST['load_contract_address'];
    if (!Account::valid($address)){
        _error("Address is invalid");
    }
    $smartContract = api_get("/api.php?q=getSmartContract&address=".$address);
    if(!$smartContract){
        _error("Address is not smart contract");
    }
    $_SESSION['contract']=$smartContract;
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

function login_contract() {
    global $engines;
    $request_code = uniqid();
    $_SESSION['auth_request_code']=$request_code;
    $redirect = urlencode($engines[$_SESSION['engine']]['atheos_url']."?login_contract=1");
    $url=NODE_URL."/dapps.php?url=".MAIN_DAPPS_ID."/wallet/auth.php?app=Athoes&request_code=$request_code&store_private_key=1&redirect=$redirect";
    $response = [
        "action" => "redirect",
        "url" => $url
    ];
    echo "json:".json_encode($response);
    exit;
}

function login_new_contract() {
    global $engines;
    $request_code = uniqid();
    $_SESSION['auth_request_code']=$request_code;
    $redirect = urlencode($engines[$_SESSION['engine']]['atheos_url']."?login_new_contract=1");
    $url=NODE_URL."/dapps.php?url=".MAIN_DAPPS_ID."/wallet/auth.php?app=Athoes&request_code=$request_code&redirect=$redirect";
    $response = [
        "action" => "redirect",
        "url" => $url
    ];
    echo "json:".json_encode($response);
    exit;
}

function select_address() {
    global $engines;
    $request_code = uniqid();
    $_SESSION['auth_request_code']=$request_code;
    $redirect = urlencode($engines[$_SESSION['engine']]['atheos_url']."?select_address=1");
    $url=NODE_URL."/dapps.php?url=".MAIN_DAPPS_ID."/wallet/auth.php?app=Athoes&request_code=$request_code&&redirect=$redirect";
    $response = [
        "action" => "redirect",
        "url" => $url
    ];
    echo "json:".json_encode($response);
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
        _error('Method type not selected');
    }

    if($method_type == "exec") {
        if(empty($fn_address)) {
            $fn_address=$_SESSION['contract']['address'];
        }
        if(!Account::valid($fn_address)) {
            _error("Address is invalid");
        }
        $type=TX_TYPE_SC_EXEC;
        $public_key = $_SESSION['account']['public_key'];
        $src=$address;
        $dst=$fn_address;
    } else if ($method_type == "send") {
        if(!$virtual) {
            $smartContract = api_get("/api.php?q=getSmartContract&address=".$_SESSION['account']['address']);
            if(!$smartContract) {
                _error('Connect smart contract wallet for call send method');
            }
        }
        $type=TX_TYPE_SC_SEND;
        $public_key = $_SESSION['account']['public_key'];
        $src=$_SESSION['account']['address'];
        $dst=$fn_address;
        if(empty($dst)) {
            _error('Destination address must be specified');
        }
        if(!Account::valid($dst)) {
            _error("Address is invalid");
        }
        if($virtual && $dst == $src) {
            _error('Invalid destination address');
        }
    }

    $exec_method = array_keys($_POST['exec_method'])[0];
    $post_params = $_POST['params'][$exec_method];
    $_SESSION['params'][$exec_method]=$post_params;

    $params = SmartContractEngine::parseCmdLineArgs($post_params);

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
            _error('Method can not be executed: '.$err);
        }
        $debug_logs = SmartContractEngine::$debug_logs;
        $_SESSION['debug_logs']=array_merge($_SESSION['debug_logs'], $debug_logs);
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
        $response = [
            "action"=>"redirect",
            "url"=>$url
        ];

        echo "json:".json_encode($response);
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
    $post_params = $_POST['params'][$view_method];
    $_SESSION['params'][$view_method]=$post_params;
    $params =  SmartContractEngine::parseCmdLineArgs($post_params);

    if($virtual) {
        SmartContractEngine::$virtual = true;
        SmartContractEngine::$smartContract = $_SESSION['contract'];
        $res = SmartContractEngine::view($sc_address, $view_method, $params, $err);
        $debug_logs = SmartContractEngine::$debug_logs;
        $_SESSION['debug_logs']['view::'.$view_method]=$debug_logs;
    } else {
        $params = base64_encode(json_encode($params));
        $sc_address = $_SESSION['contract']['address'];
        $res = api_get("/api.php?q=getSmartContractView&address=$sc_address&method=$view_method&params=$params");
    }
    if($err) {
        _error('View can not be executed: '.$err);
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
    unset($_SESSION['get_property_val'][$property]);
    if($virtual) {
        SmartContractEngine::$virtual = true;
        SmartContractEngine::$smartContract = $_SESSION['contract'];
        $val = SmartContractEngine::get($sc_address, $property, $mapkey);
    } else {
        $val = api_get("/api.php?q=getSmartContractProperty&address=$sc_address&property=$property&key=$mapkey", $error);
        if($error) {
            $_SESSION['get_property_val'][$property]['error']=$error;
        }
    }
    $_SESSION['get_property_val'][$property]['value']=$val;
    $_SESSION['get_property_val'][$property]['return']=true;
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
    unset($_SESSION['params'][$property]);
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}

function wallet_auth() {
    global $engines;
    $request_code = uniqid();
    $_SESSION['auth_request_code']=$request_code;
    $redirect = urlencode($engines[$_SESSION['engine']]['atheos_url']."?");
    $url=NODE_URL."/dapps.php?url=".MAIN_DAPPS_ID."/wallet/auth.php?app=Athoes&store_private_key=1&request_code=$request_code&redirect=$redirect";
    $response = [
        "action" => "redirect",
        "url" => $url
    ];
    echo "json:".json_encode($response);
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

function clear_debug_log() {
    unset($_SESSION['debug_logs']);
    header("location: ".$_SERVER['REQUEST_URI']);
    exit;
}
