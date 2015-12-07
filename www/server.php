<?php

#
# garage project 
#

$mysql_database = "tc";
$mysql_host = "127.0.0.1";
$mysql_user = "www";
$mysql_password = "wwwpass";


$server_version = '0.0.2';

$cmd = "no_command";    
if(isset($_GET['cmd'])) {
    $cmd = $_GET['cmd'];
}

if($cmd!="php_info")
    header('Content-type: application/json; charset=utf-8');

$debug = false;
if(isset($_GET['debug'])&&$_GET['debug']=="on") {
    $debug = true;
}  

$auth = false;
//if (isset($_REQUEST[session_name()]))
session_start();
if (isset($_SESSION['user_id']) AND $_SESSION['ip'] == $_SERVER['REMOTE_ADDR']) {
    $auth = true;
    $_SESSION['count']++;
    $date = date_create();
    $_SESSION['last_time'] = date_timestamp_get($date);
}

$return = array( 'hello' => 'ok' , 'cmd' => $cmd , 'auth' => $auth, 'time' => date("d.m.Y H:i:s"), "version" => $server_version );

if($auth) {
    $return['session_ip'] = $_SESSION['ip'];
    $return['session_begin_time'] = $_SESSION['begin_time'];
    $return['session_last_time'] = $_SESSION['last_time'];
    $return['session_count'] = $_SESSION['count'];
    $return['session_user_id'] = $_SESSION['user_id'];
}

if($debug) {
    foreach($_SERVER as $key => $val) {
        $return["ENV_".$key] = $val;
    }
}


switch ( $cmd ) {
case "hello":
    break;
case "logout":
  session_destroy();
  $return['auth'] = "logout";
  break;
case "php_info":
    phpinfo();
    break;
case "auth":// auth
    try{
        $return["auth"] = "invalid_request";
        if (isset($_GET['auth_name'])&&isset($_GET['auth_pass'])) {
            $db = mysql_connect($mysql_host, $mysql_user, $mysql_password) or 
                die(json_encode(Array($return, "error" => "Database error!")));
            mysql_select_db($mysql_database, $db); 
            $result = mysql_query("set names 'utf8'");
            $name = mysql_real_escape_string($_GET['auth_name']);
            $pass = mysql_real_escape_string($_GET['auth_pass']);
            $query = "SELECT id FROM users WHERE email='$name' AND password='$pass';";
            $return['query'] = $query;
            session_destroy();
            session_start();
            $res = mysql_query($query) 
                or die(json_encode(Array($return, "error" => "Invalid query", "mysql_error" => mysql_error(), "query" => $query)));
            if ($row = mysql_fetch_assoc($res)) {
                if(isset($row['id'])&&$row['id']!="") {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
                    $_SESSION['count'] = 0;
                    $date = date_create();
                    $_SESSION['last_time'] = date_timestamp_get($date);
                    $_SESSION['begin_time'] = $_SESSION['last_time'];
                    $auth = true;
                    $return["auth"] = "true";
                } else {
                    $auth = false;
                    $return["auth"] = "no_user";
                }
            } else {
                $return["auth"] = "no_user";   
            }
        }
    }
    catch (Exception $e) {
        if(isset($query)) $return['query'] = $query;
        $return["error"] = $e->getMessage();
    }
    break;
case "mysql_test":
    try {
        $db = mysql_connect($mysql_host, $mysql_user, $mysql_password) or 
            die(json_encode("Database error!")); 
        mysql_select_db($mysql_database, $db); 
        $result = mysql_query("set names 'utf8'");
        $query = "SELECT COUNT(id) AS ITEMS FROM users;"; 
        $result = mysql_query($query); 
        if (!$result) {
            die(json_encode('Invalid query: ' . mysql_error()));
        } else {
            $return = mysql_fetch_array($result);
        }
    }
    catch (Exception $e) {
        if(isset($query)) $return['query'] = $query;
        $return["error"] = $e->getMessage();
    }
    break;


//
// Default action
//

default :
    $return["cmd"] = "no_command"; 
}

if(isset($db))
    mysql_close($db);

if($debug&&isset($query)) 
    $return["query"] = $query;  

echo json_encode($return);

?>
