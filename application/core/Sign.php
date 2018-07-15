<?php
class CI_Sign {

    function rbac($params, $secret, $salt = 'w') {
        unset($params['sign']);
        ksort($params);
        $query = '';
        foreach($params as $k=>$v){
            @$query .= $k.'='.$v.'&';
        }
        return md5( substr(md5($query.$secret), 0, -1).$salt);
    }

}
