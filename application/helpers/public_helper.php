<?php
/*
 * 年轮 － 门店列表转换
 */
function changeStoreId($ids)
{
	$params['store_id_list'] = $ids;

	$store_id = explode(',',$ids);
	$list = array();

	$params['tms_region_type'] = 1;
	$params['tms_region_time'] = 1;

	if(count($store_id) >1)
	{
		$tms_region_type = array();
		$tms_region_time = array();
		foreach($store_id as $k=>$v)
		{
			if(strpos($v,"T"))
			{
				$nian = explode('T',$v);
				array_push($tms_region_type,$nian[1]);
				array_push($tms_region_time,$nian[2]);
				array_push($list,$nian[0]);
			}
			else
			{
				array_push($list,$v);
			}
		}

		if(count($tms_region_type) >0)
		{
			$params['tms_region_type'] = min($tms_region_type);
			$params['tms_region_time'] = min($tms_region_time);
		}
		$params['store_id_list'] = implode(',',$list);
	}

	return $params;
}

function area_refelect($region_id = 106092){
	$area_refelect = array(
        106092=>1,           //上海
		1=>2,                //江苏
		54351=>3,            //浙江
		106340=>4,           //安徽
		143949=>5,           //北京
		144005=>6,           //天津
		143983=>7,           //河北
		143967=>8,           //河南
		143996=>9,           //山西
		144035=>10,           //山东
		144039=>11,           //陕西
		144045=>12,           //吉林
		144051=>13,           //黑龙江
		144224=>14,           //辽宁
		144252=>15,           //广东
		144370=>16,           //海南
		144379=>17,           //广西
		144387=>18,           //福建
		144412=>19,           //湖南
		144443=>20,           //四川
		144522=>21,           //重庆
		144551=>22,           //云南
		144595=>23,           //贵州
		145843=>24,           //青海
		144643=>25,           //湖北
		144795=>26,           //江西
		145855=>27,           //上海崇明
		144627=>28,           //甘肃
		145874=>29,           //北京五环外
   );
	return isset($area_refelect[$region_id])?$area_refelect[$region_id]:1;
}

/**
 * @method 获取登录用户的id号
 * @param void
 * @return int $uid
 */
function get_uid() {
	try {
		if (! (isset($_REQUEST['connect_id']) && $_REQUEST['connect_id'])) throw new \Exception('登录信息已过期,请重新登录!401');
		$connect_id = $_REQUEST['connect_id'];
		session_id($connect_id);
		session_start();
		$uid = isset($_SESSION['user_detail']) && isset($_SESSION['user_detail']['id']) ? intval($_SESSION['user_detail']['id']) : 0;
		session_write_close();
		if ($uid == 0) throw new \Exception('登录信息已过期,请重新登录!402');
		return $uid;
	} catch (\Exception $e) {
		exit(json_encode(array('code' => '400', 'msg' => $e->getMessage())));
	}
}

/**
 * @method 美化html输出用
 * @param string $var
 * @return void
 */
function pr($var) {
	echo '<pre>';
	print_r($var);
	echo '</pre>';
}

/**
 * @method 强制获取指定request参数
 * @param string $key
 * @return mixed
 */
function require_request($key) {
	if (isset($_REQUEST[$key]) && $_REQUEST[$key] != null) {
		return htmlspecialchars($_REQUEST[$key]);
	} else {
		exit(json_encode(array('code' => '300', 'msg' => $key . ' is empty')));
	}
}

/**
 * @method 强制获取指定get参数
 * @param string $key
 * @return mixed
 */
function require_get($key) {
    if (isset($_GET[$key]) && $_GET[$key] != null) {
        return htmlspecialchars($_GET[$key]);
    } else {
        exit(json_encode(array('code' => '300', 'msg' => $key . ' is empty')));
    }
}

/**
 * @method 强制获取指定post参数
 * @param string $key
 * @return mixed
 */
function require_post($key) {
    if (isset($_POST[$key]) && $_POST[$key] != null) {
        return htmlspecialchars($_POST[$key]);
    } else {
        exit(json_encode(array('code' => '300', 'msg' => $key . ' is empty')));
    }
}

/**
 * @method citybox签名规则
 * @param array $params
 * @param string $secret
 * @return string
 */
function sign_citybox($params = array(), $secret = 'fruitdaysecret') {
    unset($params['sign']);
    ksort($params);
    $query = '';
    foreach ($params as $k => $v) {
        if (is_array($v))
            $v = join('', $v);
            $query .= $k . '=' . $v . '&';
    }
    return md5($query . $secret);
}