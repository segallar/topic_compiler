<?php

#
# topic compiler project 
#

$server_version = '0.1.2';

$tables_subnames = Array( 'dic' => 'word_sources' );

$cmd = "no_command";    
if(isset($_GET['cmd'])) {
    $cmd = $_GET['cmd'];
}
if(isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
}

if($cmd!="php_info")
    header('Content-type: application/json; charset=utf-8');

$GLOBAL['debug'] = false;
if(isset($_GET['debug'])&&$_GET['debug']=="on") {
    $GLOBAL['debug'] = true;
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

if($GLOBAL['debug']) {
    foreach($_SERVER as $key => $val) {
        $return["ENV_".$key] = $val;
    }
}

function mysql_conn() {
    
    // mysql settings
    $mysql_database = "tc";
    $mysql_host = "127.0.0.1";
    $mysql_user = "www";
    $mysql_password = "wwwpass";
    
    if(!isset($GLOBAL['db'])) {
        $GLOBAL['db'] = mysql_connect($mysql_host, $mysql_user, $mysql_password) or 
            die(json_encode(Array($return, "error" => "Database error!")));
        mysql_select_db($mysql_database, $GLOBAL['db']); 
        $result = mysql_query("set names 'utf8'");
    }
}

function auth_logout() {
    global $return;
    session_destroy();
    $return['auth'] = false;
    $return['mgs'] = "User logged out!";
}
    
function auth_login() {
    try{
        $return["auth"] = "invalid_request";
        if (isset($_GET['auth_name'])&&isset($_GET['auth_pass'])) {
            mysql_conn();
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
    return $return;
} 

function process_table() {
// cmd format :
//      tbl_sub, where:
//      tbl - table 
//      sub - subcommand:
//          lst - list of records
//          upd - update record
//          new - insert new record or upd command with id=-1
//          del - erase record
// GET modificators:
//      &id=[x] - record id
//      &raw=on - show raw data (in longtext)
//      &[fld]=[x] - filter value on fld. Fields must be int or varchar 
//
    
    global $cmd, $tables_subnames;
    try {
        $tbl = substr($cmd,0,strlen($cmd)-4);
        if(isset($tables_subnames[$tbl]))
            $tbl = $tables_subnames[$tbl];
        $subcmd = substr($cmd,strlen($cmd)-3);
        if(isset($tbl)) {
            mysql_conn();
            if(isset($_GET['id'])&&$_GET['id']!="") {
                $id = (int)$_GET['id'];
            }
            if(isset($_POST['id'])&&$_POST['id']!="") {
                $id = (int)$_POST['id'];
            }
            $where = " WHERE TRUE ";
            if(isset($id)) 
                $where .= " AND ( $tbl.id=$id ) ";
            // select 
            if($subcmd == "lst") {
                $result = mysql_query("SHOW TABLES;");
                $tables = Array(); 
                while($arr = mysql_fetch_array($result)) {
                    $tables[] = $arr[0];
                }
                $result = mysql_query("SHOW COLUMNS FROM $tbl;");
                $fields = "";
                $join = "";
                while($arr = mysql_fetch_assoc($result)) {
                    //check cross links to other tables
                    if(in_array($arr['Field']."s",$tables)) {
                        $fields .= $arr['Field']."s.name AS ".$arr['Field'].", ";
                        $fields .= $tbl.".".$arr['Field']." AS ".$arr['Field']."_id, ";
                        $join   .= " LEFT JOIN ".$arr['Field']."s ON $tbl.".$arr['Field']."=".$arr['Field']."s.id "; 
                    } else {
                        // normal operations
                        if(substr($arr['Type'],0,8)=="longtext"||substr($arr['Type'],0,4)=="text") {
                            if(isset($_GET['raw'])&&$_GET['raw']=='on')
                                $fields .= $tbl.".".$arr['Field'].", ";
                        }
                        if(substr($arr['Type'],0,7)=="varchar"||substr($arr['Type'],0,8)=="tinytext"
                            ||substr($arr['Type'],0,9)=="timestamp"||substr($arr['Type'],0,3)=="int") {
                            $fields .= $tbl.".".$arr['Field'].", ";
                        }
                    }
                    // check filters
                    if(isset($_GET[$arr['Field']])&&$arr['Field']!="id") {
                        if(substr($arr['Type'],0,3)=="int")
                            $where .= "AND ( $tbl.".$arr['Field']."=".(int)$_GET[$arr['Field']]." ) ";
                        if(substr($arr['Type'],0,7)=="varchar")
                            $where .= "AND ( $tbl.".$arr['Field']."=\"".
                                mysql_real_escape_string(trim((string)$_GET[$arr['Field']]))."\" ) ";
                    }
                }
                $fields = substr($fields,0,strlen($fields)-2);
                $query  = "SELECT $fields ";
                $query .= "FROM $tbl $join $where;";
                //echo $query;
                $result = mysql_query($query); 
                if (!$result) {
                    return Array("msg" => 'Invalid query: '.mysql_error()." $query");
                } else {
                    $return = "";
                    while($arr = mysql_fetch_assoc($result)) {
                        $return[] = $arr;
                    }
                }
                return $return;
            } // end of lst cmd
            if($subcmd == "del") {
                if(!isset($id)) 
                    return Array( "msg" => "no id found");
                $query = "DELETE FROM $tbl $where ;";
                $result = mysql_query($query); 
                if (!$result) {
                    return Array("msg" => 'Invalid query: ' . mysql_error());
                } else {
                    return Array("msg" => "ok");
                }
            } // end of del
            if($subcmd == "upd" && $id == -1) 
                $subcmd = "new";
            // update
            if($subcmd == "upd") {
                if(!isset($id)) 
                    return Array( "msg" => "no id found");
                $set = "";
                $result = mysql_query("SHOW COLUMNS FROM $tbl;");
                while($arr = mysql_fetch_assoc($result)) {
                    if($arr['Field']!="id") {
                        if(isset($_GET[$arr['Field']])||isset($_POST[$arr['Field']])) {
                            if(substr($arr['Type'],0,7)=="varchar"||substr($arr['Type'],0,8)=="longtext"||
                               substr($arr['Type'],0,8)=="tinytext") {
                                if(isset($_GET[$arr['Field']])) { 
                                   $val = (string)$_GET[$arr['Field']];
                                } else {
                                   $val = (string)$_POST[$arr['Field']];
                                }
                                $val = mysql_real_escape_string(trim($val));
                                $set .= " ".$arr['Field']." = \"".$val."\",";
                            } // text
                            if(substr($arr['Type'],0,3)=="int") {
                                if(isset($_GET[$arr['Field']])) { 
                                   $val = (int)$_GET[$arr['Field']];
                                } else {
                                   $val = (int)$_POST[$arr['Field']];
                                }
                                $set .= " ".$arr['Field']." = ".$val.",";
                             } // int
                        }
                    }
                    if($arr['Field']=="user") {
                        $set .= " user=".$_SESSION['user_id'].",";    
                    }
                }
                if($set!="") {
                    $set = "SET ".substr($set,0,strlen($set)-1);
                    $query = "UPDATE $tbl $set $where;";
                    $result = mysql_query($query);
                    if($result)
                        return Array( "id" => $id);
                    else 
                        return Array( "msg" => " bad update query ".$result." ".$query);
                } else {
                    return Array( "msg" => "no values to set");
                }
            } // end of upd cmd
            // insert into
            if($subcmd == "new") {
                $fields = "";
                $values = "";
                $reqflg = true;
                $result = mysql_query("SHOW COLUMNS FROM $tbl;");
                while($arr = mysql_fetch_assoc($result)) {
                    if($arr['Field']!="id"&&$arr['Field']!="user") {
                        if(isset($_GET[$arr['Field']])||isset($_POST[$arr['Field']])) {
                            if(substr($arr['Type'],0,7)=="varchar"||substr($arr['Type'],0,8)=="longtext"||
                               substr($arr['Type'],0,8)=="tinytext") {
                                if(isset($_GET[$arr['Field']])) { 
                                   $val = (string)$_GET[$arr['Field']];
                                } else {
                                   $val = (string)$_POST[$arr['Field']];
                                }
                                $val = mysql_real_escape_string(trim($val));
                                $fields .= $arr['Field'].",";
                                $values .= "\"".$val."\",";
                            } // text
                            if(substr($arr['Type'],0,3)=="int") {
                                if(isset($_GET[$arr['Field']])) { 
                                   $val = (int)$_GET[$arr['Field']];
                                } else {
                                   $val = (int)$_POST[$arr['Field']];
                                }
                                $fields .= $arr['Field'].",";
                                $values .= " ".$val.",";
                            } // int
                        }
                    }
                    if($arr['Field']=="user") {
                        $fields .= "user,";
                        $values .= " ".$_SESSION['user_id'].",";    
                    }
                }
                if($fields!="") {
                    $fields = "(".substr($fields,0,strlen($fields)-1).")";
                    $values = "(".substr($values,0,strlen($values)-1).")";
                    $query = "INSERT INTO $tbl $fields VALUES $values;";
                    $result = mysql_query($query);
                    if($result)
                        return Array( "id" => mysql_insert_id());
                    else 
                        return Array( "msg" => " bad insert query ".$result." ".$query);
                } else {
                    return Array( "msg" => "no values to set");
                }
            }
            /*
            id	    int(11) unsigned	NO	PRI	NULL	auto_increment
            word	int(11)	    NO		NULL	
            freq	int(11)	    YES		NULL	
            source	int(11)	    NO		NULL	
            ts	    timestamp	NO		CURRENT_TIMESTAMP	
            */
            
 
        }
    }
    catch (Exception $e) {
        if(isset($query)) $return['query'] = $query;
        $return["error"] = $e->getMessage();
        return $return;
    }    
}

//
// Texts actions
//

function text_analyser() { 
    try {
        mysql_conn();
        
        $id = -1;
        if(isset($_GET['id'])&&$_GET['id']!="") {
            $id = (int)$_GET['id'];
        }
        if($id < 1) break;
       
        $query  = "SELECT raw ";
        $query .= "FROM texts WHERE id=$id;";
        
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
    return $return;
}

//
//  New case block
//

if($cmd=="logout") $return = auth_logout();
if($cmd=="auth")   $return = auth_login();   
//
if($auth) {
    if($cmd=="dic_lst")         $return = process_table();
    if($cmd=="dic_upd")         $return = process_table();
    if($cmd=="dic_del")         $return = process_table();
    if($cmd=="texts_lst")       $return = process_table();
    if($cmd=="texts_new")       $return = process_table();
    if($cmd=="texts_anl")       $return = text_analyser();
    if($cmd=="sentences_lst")   $return = process_table();

}    
    
if(isset($GLOBAL['db']))
    mysql_close($GLOBAL['db']);  

echo json_encode($return);

?>
