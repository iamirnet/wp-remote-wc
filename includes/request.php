<?php


function i_amir_remote_wc_get($url, $params = null){
    return i_amir_remote_wc_exec('get', $url, $params);
}

function i_amir_remote_wc_post($url, $data = null, $params = null){
    return i_amir_remote_wc_exec('post', $url, $data, $params);
}

function i_amir_remote_wc_exec($method, $uri, $data = null, $params = null){
    $method = strtoupper($method);

    $url = join('/', [get_option('i_amir_remote_wc_host_uri', "http://87.98.232.6"), $uri]);
    $headers = [];
    if (!($uri == "users/auth")) {
        if (!get_option('i_amir_remote_wc_token', false)) {
            $result = i_amir_remote_wc_post("users/auth", [
                "username" => get_option("i_amir_remote_wc_username", "neotod"),
                "password" => get_option("i_amir_remote_wc_password", "neotodneotod"),
            ]);
            if (!$result->status) return $result;
            update_option('i_amir_remote_wc_token', $result->result->access_token);
        }
        $headers[] = "Authorization: Bearer " . get_option('i_amir_remote_wc_token', false);
    }
    $headers[] = "Content-Type: application/json";
    if ($method == 'GET' && $data && !$params)  {
        $params = $data;
        $data = null;
    }
    if ($params) $url .= '?' . http_build_query($params);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch,CURLOPT_POST, $method != 'GET' );
    if ($data) curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode((object)$data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2 GTB5');
    $res = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if($error){
        return (object)['status' => false, 'message' => curl_error($ch)];
    }else{
        $res = json_decode($res);
        if ($res->is_success || $res->access_token) {
            return (object)['status' => true, 'message' => 'OK', 'result' => $res->is_success ? $res->result : $res, 'url' => $url];
        }else {
            if (!($uri == "users/auth")) {
                $result = i_amir_remote_wc_post("users/auth", [
                    "username" => get_option("i_amir_remote_wc_username"),
                    "password" => get_option("i_amir_remote_wc_password"),
                ]);
                if (!$result->status) return $result;
                update_option('i_amir_remote_wc_token', $result->result->access_token);
            }
            return (object)['status' => false, 'message' => $res->msg];
        }
    }
}