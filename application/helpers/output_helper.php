<?php
include_once(APPPATH.'libraries/Aes.php');
include_once(APPPATH.'libraries/Cryaes.php');
    function o2o_output($response)
    {
        $aes = new Aes();
        if($response['code'] == '200'){
            $response['msg']  =  $response['msg']?$response['msg']:"成功";
            $response['data'] = urlencode($aes->AesEncrypt(json_encode($response, JSON_UNESCAPED_UNICODE), base64_decode(POOL_O2O_AES_KEY)));
        }else{
            $response['msg']  =  $response['msg'];
        }
        echo stripslashes(json_encode($response, JSON_UNESCAPED_UNICODE));
        exit;
    }

    function crm_output($response)
    {
        $cryaes = new Cryaes();
        $cryaes->set_key(CRM_DATA_SECRET);
        $cryaes->require_pkcs5();
        $decString = $cryaes->encrypt(json_encode($response));
        echo $decString;
        exit;
    }

    function oms_output($response)
    {
        $aes = new Aes();
        if($response['code'] == '200'){
            $response['result'] = 1;
            $response['msg'] or $response['msg']  =  "成功";
        }else{
            $response['result'] = 0;
            $response['msg']  =  $response['msg'];
        }
        unset($response['code']);
        $response = json_encode($response);
        $params = array(
            'data' => $aes->AesEncrypt($response),
            'signature' => $aes->data_hash($response),
        );
        echo stripslashes(json_encode($params));
        exit;
    }

    function pms_output($response)
    {
        $aes = new Aes();
        if($response['code'] == '200'){
            $response['result'] = 1;
            $response['msg'] or $response['msg']  =  "成功";
        }else{
            $response['result'] = 0;
            $response['msg']  =  $response['msg'];
        }
        unset($response['code']);
        $response = json_encode($response);
        $params = array(
            'data' => $aes->AesEncrypt($response),
            'signature' => $aes->data_hash($response),
        );
        echo stripslashes(json_encode($params));
        exit;
    }

    function oa_output($response)
    {
        $aes = new Aes();
        if($response['code'] == '200'){
            $response['result'] = 1;
            $response['msg'] or $response['msg']  =  "成功";
        }else{
            $response['result'] = 0;
            $response['msg']  =  $response['msg'];
        }
        unset($response['code']);
        $response = json_encode($response);
        $params = array(
            'data' => $aes->AesEncrypt($response,base64_decode(OA_AES_KEY)),
            'signature' => $aes->data_hash($response,OA_HASH_KEY),
        );
        echo stripslashes(json_encode($params));
        exit;
    }