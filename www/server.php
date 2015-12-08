<?php

#
# garage project 
#

$mysql_database = "tc";
$mysql_host = "127.0.0.1";
$mysql_user = "www";
$mysql_password = "wwwpass";

$server_version = '0.0.1';

$cmd = "no_command";    
if(isset($_GET['cmd'])) {
    $cmd = $_GET['cmd'];
}
if(isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
}

if($cmd!="php_info")
    header('Content-type: application/json; charset=utf-8');

$debug = false;
if(isset($_GET['debug'])&&$_GET['debug']=="on") {
    $debug = true;
}  

// auth test
$auth = false;
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
    // debug info
    $return['session_begin_time_h'] = gmdate("Y-m-d H:i:s",$_SESSION['begin_time']);
    $return['session_last_time_h'] = gmdate("Y-m-d H:i:s",$_SESSION['last_time']);
    $return['session_time_delta'] = ($_SESSION['last_time']-$_SESSION['begin_time']) / (60*60);
}

if($debug) {
    foreach($_SERVER as $key => $val) {
        $return["ENV_".$key] = $val;
    }
}

switch ( $cmd ) {
case "hello":
    break;
//
// Texts actions
//
case "txt_upload": // store text into db
                   // POST method
     try {
        $db = mysql_connect($mysql_host, $mysql_user, $mysql_password) or 
            die(json_encode("Database error!")); 
        mysql_select_db($mysql_database, $db); 
        $result = mysql_query("set names 'utf8'");
        
        if(isset($_POST["text"])&&$_POST["text"]!="") {
            $text = $_POST["text"];
            $text1 = "";
            $offset = 0;
            $text_count = 1;
            
            $i = true; 
            while( ! ($i === false) ) {
                $i = strpos($text,"<br />",$offset);
                if( $i === false ) {
                    $str = substr($text,$offset);
                } else {
                    $str = substr($text,$offset,$i-$offset);
                }
                $text1 .= "<p align=left><a href=\"#top\"><span class=\"glyphicon glyphicon-arrow-up\" ";
                $text1 .= " aria-hidden=\"true\"></span></a><b><a name=\"text-$text_count\"></a>";
                $text1 .= $text_count."</b> $str</p>";
                $offset = $i + 6;
                $text_count++;   
            }
            $return = Array($text1);
        } else {
            $return["msg"] = "no text";
            break;
        }
         
        /*
        $query = "SELECT COUNT(id) AS ITEMS FROM users;"; 
        $result = mysql_query($query); 
        if (!$result) {
            die(json_encode('Invalid query: ' . mysql_error()));
        } else {
            $return = mysql_fetch_array($result);
        }
        */
         
    }
    catch (Exception $e) {
        if(isset($query)) $return['query'] = $query;
        $return["error"] = $e->getMessage();
    }
    break;
//
// Test actions
//
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
case "php_info":
    phpinfo();
    break;
//
// auth actions
//
case "logout":
  session_destroy();
  $return['auth'] = false;
  $return['mgs'] = "User logged out!";
  break;
case "auth":
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
                    $return["msg"] = "Authentication successful.";
                    $return["auth"] = true;
                } else {
                    $auth = false;
                    $return["auth"] = false;
                    $return["msg"] = "No such user found.";
                }
            } else {
                $return["auth"] = false;
                $return["msg"] = "No such user found.";  
            }
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
