<?php
defined('BASEPATH') OR exit('No direct script access allowed');
include_once(APPPATH.'libraries/Aes.php');
include_once(APPPATH.'libraries/Poolhash.php');
include_once(APPPATH.'libraries/Cryaes.php');
/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;


// RBAC路由
$route['rbac/(.*)'] = function ($version) {

	// check sign
	$Sign = load_class('Sign', 'core');
	$sign1 = $Sign->rbac($_REQUEST, API_SECRET, 'w');
	$sign2 = $Sign->rbac($_REQUEST, PRO_SECRET, 'w');

	if( $sign1 != $_REQUEST['sign'] && $sign2 != $_REQUEST['sign']) {
		header('HTTP/1.1 500 Internal Server Error');
		echo json_encode(['msg'=>'Invalid API sign']);
		exit;
	}

	unset($_REQUEST['sign']);
	list($service, $version, $controller, $method) =  explode('.', $_REQUEST['service']);
	unset($_REQUEST['service']);

	$CFG =& load_class('Config', 'core');
	$CFG->load('service');
	$url = 'http://' . $CFG->item($service, 'service');
	$restclient = load_class('Restclient', 'core');
	$restclient->set_option('base_url', $url);
	$result = $restclient->post("/{$version}/{$controller}/{$method}", $_REQUEST);
	exit($result->response);
};


//nirvana3用的路由
$route['(app|active|spa)/(.*)'] = function ($request1, $request2) {
	try {
		if (validate_nirvana($returnParam) == false) throw new \Exception('签名错误');
		if (!isset($_REQUEST['service'])) throw new \Exception('service 参数错误1');
		$service = htmlspecialchars($_REQUEST['service']);//cart.get
		$service_arr = explode('.', $service);
		if ( !isset($service_arr[1]) ) throw new \Exception('service 参数错误2');
		$method = $service_arr[1];//get
		$class = $service_arr[0];//cart

		defined('API_VERSION_FOLDER') OR define('API_VERSION_FOLDER', 'v2');
		defined('NIVANA3') OR define('NIVANA3', true);
		$path_info = $request1 . '/' . $request2;
		$return_router = $path_info . '/' . $class . '/' . $method;// app/v2/cart/get
		if ($class == 'cart_v2') $return_router = $path_info . '/cart/' . $method;//@TODO, 2017-04-18;为老版本参数 service=cart_v2.get 这种格式做兼容
		return $return_router;
	} catch (\Exception $e) {
        $response['code'] = $returnParam['code']?$returnParam['code']:300;
        $response['msg'] = $returnParam['msg']?$returnParam['msg']:$e->getMessage();
		exit(json_encode($response));
	}
};

//static路由
$route['static/v2'] = function () {
    try {
        if (validate_nirvana() == false) throw new \Exception('签名错误');
        if (!isset($_REQUEST['service'])) throw new \Exception('service 参数错误');
        $service = htmlspecialchars($_REQUEST['service']);//cart.get
        $service_arr = explode('.', $service);
        if ( !isset($service_arr[1]) ) throw new \Exception('service 参数错误');
        $method = $service_arr[1];//get
        $class = $service_arr[0];//cart

        defined('API_VERSION_FOLDER') OR define('API_VERSION_FOLDER', 'v2');
        defined('NIVANA3') OR define('NIVANA3', true);
        $return_router = 'static/v2/' . $class . '/' . $method;// app/v2/cart/get
        return $return_router;
    } catch (\Exception $e) {
// 		$reutnParam['code'] = $e->getMessage();
        $response['msg'] = $e->getMessage();
        exit(json_encode($response));
    }
};

//POS用的路由
$route['pos/v1'] = function () {
	try {
		if (validate_pos() == false) throw new \Exception('签名错误');
		if (!isset($_REQUEST['service'])) throw new \Exception('service 参数错误');
		$service = htmlspecialchars($_REQUEST['service']);
		$service_arr = explode('.', $service);
		if ( !isset($service_arr[1]) ) throw new \Exception('service 参数错误');
		$method = $service_arr[1];//get
		$class = $service_arr[0];//cart

		$return_router = 'pos/v1/' . $class . '/' . $method;
        defined('API_VERSION_FOLDER') OR define('API_VERSION_FOLDER', 'pos/v1');
		return $return_router;
	} catch (\Exception $e) {
// 		$reutnParam['code'] = $e->getMessage();
		$response['msg'] = $e->getMessage();
		exit(json_encode($response));
	}
};

//OMS用的路由
$route['oms/v1'] = function () {
	try {
		if (validata_oms() == false) throw new \Exception('签名错误');
		if (!isset($_REQUEST['method'])) throw new \Exception('method 参数错误');
		$service = htmlspecialchars($_REQUEST['method']);
		$service_arr = explode('.', $service);
		if ( !isset($service_arr[1]) ) throw new \Exception('method 参数错误');
		$method = $service_arr[1];//get
		$class = $service_arr[0];//cart

		$return_router = 'oms/v1/' . $class . '/' . $method;
		return $return_router;
	} catch (\Exception $e) {
// 		$reutnParam['code'] = $e->getMessage();
		$response['msg'] = $e->getMessage();
		exit(json_encode($response));
	}
};


//O2O用的路由
$route['o2o/v1'] = function () {
	try {
		if (validata_o2o() == false) throw new \Exception('签名错误');
		if (!isset($_REQUEST['appId']) || $_REQUEST['appId'] != POOL_O2O_APPID) throw new \Exception('appid错误');
		if (!isset($_REQUEST['method'])) throw new \Exception('缺少系统级参数');
		//if (!in_array($_REQUEST['method'], $o2o_allowed_servers)) throw new \Exception('接口不支持');
		if (!isset($_REQUEST['v']) || $_REQUEST['v'] != POOL_O2O_VERSION) throw new \Exception('版本号错误');
		$service = htmlspecialchars($_REQUEST['method']);
		$service_arr = explode('.', $service);
		if ( !isset($service_arr[1]) ) throw new \Exception('method 参数错误');
		$method = $service_arr[1];//get
		$class = $service_arr[0];//cart

		$return_router = 'o2o/v1/' . $class . '/' . $method;

		return $return_router;
	} catch (\Exception $e) {
// 		$reutnParam['code'] = $e->getMessage();
		$response['msg'] = $e->getMessage();
		exit(json_encode($response));
	}
};

//CRM用的路由
$route['crm/v1'] = function () {
	try {
		if (validata_crm($returnParam) == false) throw new \Exception('签名错误');
		if (!isset($returnParam['service'])) throw new \Exception('缺少系统级参数');
		if (!isset($returnParam['timestamp'])) throw new \Exception('缺少系统级参数');
		$_POST = $returnParam;
		$service = htmlspecialchars($returnParam['service']);
		$service_arr = explode('.', $service);
		if ( !isset($service_arr[1]) ) throw new \Exception('method 参数错误');
		$method = $service_arr[1];//get
		$class = $service_arr[0];//cart

		$return_router = 'crm/v1/' . $class . '/' . $method;

		return $return_router;
	} catch (\Exception $e) {
// 		$reutnParam['code'] = $e->getMessage();
		$response['msg'] = $e->getMessage();
		exit(json_encode($response));
	}
};

//CITYBOX用的路由
$route['citybox/v1'] = function () {
    try {
        if (validate_citybox() == false) throw new \Exception('签名错误');
        if (!isset($_REQUEST['service'])) throw new \Exception('service 参数错误');

        if (!isset($_REQUEST['timestamp'])) throw new \Exception('缺少系统级参数');
        if (!isset($_REQUEST['source'])) throw new \Exception('缺少系统级参数');
//        if (!in_array($_REQUEST['source'],array('wap','app'))) throw new \Exception('service 参数错误');

        $service = htmlspecialchars($_REQUEST['service']);
        $service_arr = explode('.', $service);
        if (!isset($service_arr[1])) throw new \Exception('service 参数错误');
        $method = $service_arr[1];//get
        $class = $service_arr[0];//cart

        $return_router = 'citybox/v1/' . $class . '/' . $method;

        return $return_router;
    } catch (\Exception $e) {
// 		$reutnParam['code'] = $e->getMessage();
        $response['msg'] = $e->getMessage();
        exit(json_encode($response));
    }
};

//TMS用的路由
$route['tms/v1'] = function () {
	try {
		if (validate_tms() == false) throw new \Exception('签名错误');
		if (!isset($_REQUEST['service'])) throw new \Exception('service 参数错误');
		if (!isset($_REQUEST['v'])) throw new \Exception('缺少系统级参数v');
		if (!isset($_REQUEST['appKey'])) throw new \Exception('缺少系统级参数appKey');
		if (!isset($_REQUEST['ts'])) throw new \Exception('缺少系统级参数ts');
		$service = htmlspecialchars($_REQUEST['service']);
		$service_arr = explode('.', $service);
		if ( !isset($service_arr[1]) ) throw new \Exception('service 参数错误');
		$method = $service_arr[1];//get
		$class = $service_arr[0];//cart

		$return_router = 'tms/v1/' . $class . '/' . $method;

		return $return_router;
	} catch (\Exception $e) {
// 		$reutnParam['code'] = $e->getMessage();
		$response['msg'] = $e->getMessage();
		exit(json_encode($response));
	}
};

//OA用的路由
$route['oa/v1'] = function () {
	try {
		if (validata_oa() == false) throw new \Exception('签名错误');
		if (!isset($_REQUEST['method'])) throw new \Exception('method 参数错误');
		$service = htmlspecialchars($_REQUEST['method']);
		$service_arr = explode('.', $service);
		if ( !isset($service_arr[1]) ) throw new \Exception('method 参数错误');
		$method = $service_arr[1];//get
		$class = $service_arr[0];//cart

		$return_router = 'oa/v1/' . $class . '/' . $method;
		return $return_router;
	} catch (\Exception $e) {
// 		$reutnParam['code'] = $e->getMessage();
		$aes = new Aes();
		$response['result'] = 0;
		$response['msg'] = $e->getMessage();
		$response = json_encode($response);
        $params = array(
            'data' => $aes->AesEncrypt($response,base64_decode(OA_AES_KEY)),
            'signature' => $aes->data_hash($response,OA_HASH_KEY),
        );
        echo stripslashes(json_encode($params));
        exit;
	}
};

//PMS用的路由
$route['pms/v1'] = function () {
	try {
		if (validata_pms() == false) throw new \Exception('签名错误');
		if (!isset($_REQUEST['method'])) throw new \Exception('method 参数错误');
		$service = htmlspecialchars($_REQUEST['method']);
		$service_arr = explode('.', $service);
		if ( !isset($service_arr[1]) ) throw new \Exception('method 参数错误');
		$method = $service_arr[1];//get
		$class = $service_arr[0];//cart

		$return_router = 'pms/v1/' . $class . '/' . $method;
		return $return_router;
	} catch (\Exception $e) {
// 		$reutnParam['code'] = $e->getMessage();
		$aes = new Aes();
		$response['result'] = 0;
		$response['msg'] = $e->getMessage();
		$response = json_encode($response);
        $params = array(
            'data' => $aes->AesEncrypt($response),
            'signature' => $aes->data_hash($response),
        );
        echo stripslashes(json_encode($params));
        exit;
	}
};

$route['cron/(.*)$'] = function ($request) {
	if (!is_cli()) exit('未知错误');
	return 'cron/' . $request;
};

/**
 * @example
 * $_POST['service']	= 'cart_v2.get';
 * $_POST['source']		= 'app';
 */
$route['^(.*)$'] = function ($request) {
	try {
		if (validate_nirvana() == false) throw new \Exception('签名错误');
		if ( ! (isset($_REQUEST['service']) && isset($_REQUEST['source'])) ) throw new \Exception('410');
		$service = htmlspecialchars($_REQUEST['service']);//cart_v2.get
		$source = htmlspecialchars($_REQUEST['source']);//app
		$service_arr = explode('.', $service);
		if ( !isset($service_arr[1]) ) throw new \Exception('411');
		$method = $service_arr[1];//get
		$route_info = explode('_', $service_arr[0]);
		if ( !isset($route_info[1]) ) throw new \Exception('412');
		$class = $route_info[0];//cart
		$api_version = $route_info[1];//v2
		defined('API_VERSION_FOLDER') OR define('API_VERSION_FOLDER', $api_version);
		$file_path = APPPATH . '/controllers/' . $source . '/' . $api_version . '/' . ucfirst($class) . '.php';
		if ( !file_exists($file_path) ) throw new \Exception('413');
		$return_route = $source . '/' . $api_version . '/' . $class . '/' . $method;// app/v2/cart/get
		return $return_route;
	} catch (Exception $e) {
		$response['code'] = $e->getMessage();
		$response['response'] = '参数错误';
		exit(json_encode($response));
	}
};

//验签nirvana2及nirvana3
function validate_nirvana(&$returnParam = array()) {
	/*为api/test提供, start */
// 	if (stristr($_SERVER['REQUEST_URI'], '/test')) {
	if (isset($_SERVER['CI_ENV']) && $_SERVER['CI_ENV'] == 'development') {
		// dev和staging环境才可以访问
		$server_name     = php_uname("n");
		$allowed_servers = ['ip-10-0-1-236', 'ip-10-0-1-55'];
		if( !in_array($server_name, $allowed_servers) ) {
			die("apidoc machine not allowed");
		} else {
			return true;
		}
	}
	/* 为api/test提供, end */

// 	$post_param = $this->input->post();
	$post_param = $_POST;
	$request_sign = isset($post_param['sign']) ? $post_param['sign'] : '';
	unset($post_param['sign']);
	if (!$post_param) return true;//如果纯get参数,不走验签
	if ($_REQUEST['service'] == 'order.apply_return_exchange') return true;//spa这个接口由于node.js转发的未知原因不能验签
	ksort($post_param);
	$query = '';
	foreach($post_param as $k => $v) {
// 		if ($v == '') continue;//@TODO,去掉这行，前端会把空的数据传过来也需要验签
		$query .= $k . '=' . $v . '&';
	}
	$validate_sign1 = md5(substr(md5($query.API_SECRET), 0,-1).'w');
	$validate_sign2 = md5(substr(md5($query.PRO_SECRET), 0,-1).'w');
	if ($validate_sign1 == $request_sign || $validate_sign2 == $request_sign) {//@TODO,需要支持两种签名同时存在,需求来自于安卓开发团队
		$bool = true;
	} else {
		$returnParam['code']	= '300';//@TODO
		$returnParam['msg']	= '签名错误';
		$bool = false;
		return $bool;
	}
	if(isset($post_param['version']) && isset($post_param['source']) && strtolower($post_param['source']) == 'app' && $post_param['version'] < '5.7.0'){
		$returnParam['code']	= '900';//@TODO
		$returnParam['msg']	= '发现新版本，更多优惠更好推荐，还有神秘游戏等你玩哟～';
		$bool = false;
		return $bool;
	}
	return $bool;
}

//验签POS
function validate_pos(&$returnParam = array()) {

// 	$post_param = $this->input->post();
	$post_param = $_POST;
	$request_sign = isset($post_param['sign']) ? $post_param['sign'] : '';
	unset($post_param['sign']);
	ksort($post_param);
	$query = '';
	foreach($post_param as $k => $v) {
		//if ($v == '') continue;
		$query .= $k . '=' . $v . '&';
	}
	$validate_sign = md5(substr(md5($query.POS_SECRET), 0,-1).'P');//@TODO
	if ($validate_sign == $request_sign) {
		$bool = true;
	} else {
		$returnParam['code']	= '300';//@TODO
		$returnParam['msg']	= '签名错误';
		$bool = false;
	}
	return $bool;
}

//验签O2O
function validata_o2o(&$returnParam = array()){
	$aes = new Aes();
	$poolhash = new Poolhash();
	$params = $_POST;
	if(isset($params['data']))
        $params['data'] = urldecode($params['data']);
    $sign = $params['sign'];
    unset($params['sign']);
    $params['data'] = $aes->AesDecrypt($params['data'], base64_decode(POOL_O2O_AES_KEY));
    $bool = true;
    if (!$poolhash->check_sign($params, $sign, POOL_O2O_SECRET)) {
        $returnParam['result']	= '0';//@TODO
		$returnParam['msg']	= '签名错误';
		$bool = false;
    }
    return $bool;
}

//验签OMS
function validata_oms(&$returnParam = array()){
	$aes = new Aes();
	$params = $_POST;
	if(isset($params['data']))
        $data = str_replace(' ','+',trim($params['data']));
    if(!isset($params['signature']) || empty($params['signature'])){
    	$returnParam['result']	= '0';//@TODO
		$returnParam['msg']	= '缺少signature';
		$bool = false;
		return $bool;
    }
    $data = $aes->AesDecrypt($data);
    $signature = trim($params['signature']);
    $data_hash = $aes->data_hash($data);
    $bool = true;
    if ($data_hash !== $signature) {
        $returnParam['result']	= '0';//@TODO
		$returnParam['msg']	= '签名错误';
		$bool = false;
    }
    return $bool;
}

//验签OA
function validata_oa(&$returnParam = array()){
	$aes = new Aes();
	$params = $_POST;
	if(isset($params['data']))
        $data = str_replace(' ','+',trim($params['data']));
    if(!isset($params['signature']) || empty($params['signature'])){
    	$returnParam['result']	= '0';//@TODO
		$returnParam['msg']	= '缺少signature';
		$bool = false;
		return $bool;
    }
    $data = $aes->AesDecrypt($data,base64_decode(OA_AES_KEY));
    $signature = trim($params['signature']);
    $data_hash = $aes->data_hash($data,OA_HASH_KEY);
    $bool = true;
    if ($data_hash !== $signature) {
        $returnParam['result']	= '0';//@TODO
		$returnParam['msg']	= '签名错误';
		$bool = false;
    }
    return $bool;
}

//验签PMS
function validata_pms(&$returnParam = array()){
	$aes = new Aes();
	$params = $_POST;
	if(isset($params['data']))
        $data = str_replace(' ','+',trim($params['data']));
    if(!isset($params['signature']) || empty($params['signature'])){
    	$returnParam['result']	= '0';//@TODO
		$returnParam['msg']	= '缺少signature';
		$bool = false;
		return $bool;
    }
    $data = $aes->AesDecrypt($data);
    $signature = trim($params['signature']);
    $data_hash = $aes->data_hash($data);
    $bool = true;
    if ($data_hash !== $signature) {
        $returnParam['result']	= '0';//@TODO
		$returnParam['msg']	= '签名错误';
		$bool = false;
    }
    return $bool;
}

//验签CRM
function validata_crm(&$returnParam = array()){
	$params = $_POST;
	$cryaes = new Cryaes();
	$cryaes->set_key(CRM_DATA_SECRET);
    $cryaes->require_pkcs5();
    $decString = $cryaes->decrypt($params['data']);
    $data = json_decode($decString,true);

    $sign = $data['sign'];
    unset($data['sign']);
    ksort($data);
    $query = '';
    foreach($data as $k=>$v){
        $query .= $k.'='.$v.'&';
    }
    $validate_sign = md5(substr(md5($query.CRM_SECRET), 0,-1).'w');
    $pro_validate_sign = md5(substr(md5($query.CRM_SECRET), 0,-1).'w');
    $bool = true;
    if($validate_sign!=$sign && $pro_validate_sign!=$sign){
    	$returnParam['code']	= '0';//@TODO
		$returnParam['msg']	= '签名错误';
		$bool = false;
    }
    $returnParam = $data;
    return $bool;
}


//验签CITYBOX
function validate_citybox(&$returnParam = array()) {

// 	$post_param = $this->input->post();
    $post_param = $_POST;
    $request_sign = isset($post_param['sign']) ? $post_param['sign'] : '';
    unset($post_param['sign']);
    ksort($post_param);
    $query = '';
    foreach($post_param as $k => $v) {
        //if ($v == '') continue;
        $query .= $k . '=' . $v . '&';
    }
    $validate_sign = md5(substr(md5($query.CITYBOX_SECRET), 0,-1).'C');
    if ($validate_sign == $request_sign) {
        $bool = true;
    } else {
        $returnParam['code']	= '300';
        $returnParam['msg']	= '签名错误';
        $bool = false;
    }
    return $bool;
}

//验签TMS
function validate_tms(&$returnParam = array()) {

// 	$post_param = $this->input->post();
	$post_param = $_POST;
	$request_sign = isset($post_param['sign']) ? $post_param['sign'] : '';
	unset($post_param['sign']);
	ksort($post_param);
	$query = '';
	foreach($post_param as $k => $v) {
		$query .= $k . $v;
	}
	$validate_sign = sha1($query . POOL_O2O_TMS_SECRET);//@TODO
	$validate_sign = strtoupper($validate_sign);
	if ($validate_sign == $request_sign) {
		$bool = true;
	} else {
		$returnParam['code']	= '300';//@TODO
		$returnParam['msg']	= '签名错误';
		$bool = false;
	}
	return $bool;
}