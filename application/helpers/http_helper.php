<?php

function params($name, $options = [], $default = null) {

    $ci = &get_instance();
    $value = null;
    if( is_string($options) )
        $options = explode(',', $options);
    if( !$options )
        $options = [];

    if( $ci->input->get($name) )
        $value = $ci->input->get($name);
    if( $ci->input->input_stream($name) )
        $value = $ci->input->input_stream($name);

    if( in_array('explode', $options) )
        // if( strstr($value, ',') )
            if( $value )
                $value = explode(',', $value);

    if( in_array('require', $options) )
        if( empty($value) ) {
            send(400, ['err'=>"缺少{$name}"]);
            exit;
        }

    if(!$value)
        $value = $default;

    return $value;

}

function send($status_code, $data) {

    $ci = &get_instance();

    $ci->output
        ->set_status_header($status_code)
        ->set_content_type('application/json')
        ->set_output(json_encode($data))
        ->_display();

    exit;
}
