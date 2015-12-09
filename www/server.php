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
    $user_id = $_SESSION['user_id'];
}

$return = array( 'hello' => 'ok' , 'cmd' => $cmd , 'auth' => $auth, 'time' => date("d.m.Y H:i:s"), "version" => $server_version );

if($auth) {
    $return['session_ip'] = $_SESSION['ip'];
    $return['session_begin_time'] = $_SESSION['begin_time'];
    $return['session_last_time'] = $_SESSION['last_time'];
    $return['session_count'] = $_SESSION['count'];
    $return['session_user_id'] = $_SESSION['user_id'];
    $return['session_user_name'] = $_SESSION['user_name'];
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
case "get_text_list":
    try {
        if(!$auth) 
            break;
        $db = mysql_connect($mysql_host, $mysql_user, $mysql_password) or 
            die(json_encode("Database error!")); 
        mysql_select_db($mysql_database, $db); 
        $result = mysql_query("set names 'utf8'");
        
        $query  = "SELECT t.id, t.lang, l.name AS lname, t.name AS tname, t.author, t.ts, u.name AS uname, t.processed ";
        $query .= "FROM texts t, langs l, users u WHERE l.id=t.lang AND u.id=t.user;";
        
        $result = mysql_query($query); 
        if (!$result) {
            die(json_encode('Invalid query: ' . mysql_error()));
        } else {
            $return = "";
            while($arr = mysql_fetch_assoc($result)) {
                $return[] = $arr;
            }
        }
    }
    catch (Exception $e) {
        if(isset($query)) $return['query'] = $query;
        $return["error"] = $e->getMessage();
    }
    break;            
case "get_text_raw" :
   try {
        if(!$auth) 
            break;
        $db = mysql_connect($mysql_host, $mysql_user, $mysql_password) or 
            die(json_encode("Database error!")); 
        mysql_select_db($mysql_database, $db); 
        $result = mysql_query("set names 'utf8'");
        
        $id = -1;
        if(isset($_GET['id'])&&$_GET['id']!="") {
            $id = (int)$_GET['id'];
        }
        if($id < 1) break;
       
        $query  = "SELECT raw ";
        $query .= "FROM texts t WHERE t.id=$id;";
        
        $result = mysql_query($query); 
        if (!$result) {
            die(json_encode('Invalid query: ' . mysql_error()));
        } else {
            $return = mysql_fetch_assoc($result);
        }
    }
    catch (Exception $e) {
        if(isset($query)) $return['query'] = $query;
        $return["error"] = $e->getMessage();
    }
    break;    
case "get_text_processed" :
   try {
        if(!$auth) 
            break;
        $db = mysql_connect($mysql_host, $mysql_user, $mysql_password) or 
            die(json_encode("Database error!")); 
        mysql_select_db($mysql_database, $db); 
        $result = mysql_query("set names 'utf8'");
        
        $id = -1;
        if(isset($_GET['id'])&&$_GET['id']!="") {
            $id = (int)$_GET['id'];
        }
        if($id < 1) { 
            $return["msg"] = "no id found";
            break; 
        }
       
        $query  = "SELECT s.id, s.sequence ,s.raw  ";
        $query .= "FROM sentences s WHERE s.text=$id ORDER BY s.sequence;";
        
        $result = mysql_query($query); 
        if (!$result) {
            die(json_encode('Invalid query: ' . mysql_error()));
        } else {
            $return = "";
            while($arr = mysql_fetch_assoc($result)) {
                $return[] = $arr;
            }
        }
    }
    catch (Exception $e) {
        if(isset($query)) $return['query'] = $query;
        $return["error"] = $e->getMessage();
    }
    break;    
case "text_analyse":
  try {
        if(!$auth) 
            break;
        $db = mysql_connect($mysql_host, $mysql_user, $mysql_password) or 
            die(json_encode("Database error!")); 
        mysql_select_db($mysql_database, $db); 
        $result = mysql_query("set names 'utf8'");
        
        $id = -1;
        if(isset($_GET['id'])&&$_GET['id']!="") {
            $id = (int)$_GET['id'];
        }
        if($id < 1) break;
       
        $query  = "SELECT raw ";
        $query .= "FROM texts t WHERE t.id=$id;";
        
        $result = mysql_query($query); 
        if (!$result) {
            die(json_encode('Invalid query: ' . mysql_error()));
        } else {
            // analyse 
            $text = mysql_fetch_assoc($result)['raw'];
            $offset = 0;
            $text_count = 1;
            $i = true;
            $insert_result = true;
            while( ! ($i === false) ) {
                $i = strpos($text,"<br />",$offset);
                if( $i === false ) {
                    $str = mysql_real_escape_string(substr($text,$offset));
                } else {
                    $str = mysql_real_escape_string(substr($text,$offset,$i-$offset));
                }
                $query  = "INSERT INTO sentences (text,sequence,raw) VALUES ";
                $query .= " ( $id, $text_count, \"$str\" );";
                $result = mysql_query($query); 
                if (!$result) {
                    $insert_result = false;
                }
                $offset = $i + 6;
                $text_count++;   
            }
            if($insert_result) {
                $query  = "UPDATE texts SET processed=1 WHERE id=$id;";
                $insert_result = mysql_query($query); 
            }
            $return["msg"] = $insert_result;
        }
    }
    catch (Exception $e) {
        if(isset($query)) $return['query'] = $query;
        $return["error"] = $e->getMessage();
    }
    break;              
case "text_upload": // store text into db
                   // POST method
     if(!$auth) 
         break;
     try {
        $db = mysql_connect($mysql_host, $mysql_user, $mysql_password) or 
            die(json_encode("Database error!")); 
        mysql_select_db($mysql_database, $db); 
        $result = mysql_query("set names 'utf8'");
        
        if(isset($_POST["text"])&&$_POST["text"]!="") {
            $text = $_POST["text"];       
            if(isset($_POST["name"])) {
                $text_name = mysql_real_escape_string(trim($_POST["name"]));
            } else {
                $text_name = "";
            }
            if(isset($_POST["author"])) {
                $text_author = mysql_real_escape_string(trim($_POST["author"]));
            } else {
                $text_author = "";
            }
            $text_raw = mysql_real_escape_string($text);
            $query  = "INSERT INTO texts (lang,name,author,user,raw) VALUES ";
            $query .= "(1,\"$text_name\",\"$text_author\",".$user_id.",\"$text_raw\");"; 
            $result = mysql_query($query);
            if($result) {
                $return['id'] = mysql_insert_id();
            } else {
                $return["msg"] = "insert error";
            }
        } else {
            $return["msg"] = "no text";
            break;
        }
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
            $query = "SELECT id, name FROM users WHERE email='$name' AND password='$pass';";
            //$return['query'] = $query;
            session_destroy();
            session_start();
            $res = mysql_query($query) 
                or die(json_encode(Array($return, "error" => "Invalid query", "mysql_error" => mysql_error(), "query" => $query)));
            if ($row = mysql_fetch_assoc($res)) {
                if(isset($row['id'])&&$row['id']!="") {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
                    $_SESSION['count'] = 0;
                    $_SESSION['user_name'] = $row['name'];
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
