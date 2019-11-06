<?php

/*
 curl --insecure https://192.168.1.1:8079/settings/system_wide_options/enable_sshd=1?sid=yeVb7xuM
*/

/* display ALL errors */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/* Include configuration */
include("config.php");

include_once("restclient.php");

function client_api_init($api_url)
{
	$api = new RestClient([
		'base_url' => $api_url
		,'headers' =>['Accept'=>"application/json"]
		,'curl_options'=>array(CURLOPT_FOLLOWLOCATION=>true
								,CURLOPT_SSL_VERIFYPEER=>false
								,CURLOPT_SSL_VERIFYHOST=>false)
		//,'format' => "json"
	]);
	//allows you to receive decoded JSON data as an array.
	$api->register_decoder('json', function($data){
		$comment_position = strripos($data, '/*');
		if($comment_position)
			$data = substr($data, 0, $comment_position);	//removing comment tail from data
		return json_decode($data, TRUE);
	});
	
	return $api;
}

function client_trassir_login($api,$api_username,$api_password)
{
	if($api==null)
		return false;
	
	/*
		https://192.168.1.200:8080/login?username=Admin&password=987654321
	*/
	
	$result = $api->get("/login", ['username' => $api_username,'password'=>$api_password]);
	if($result->info->http_code != 200)
	{
		var_dump($result);
		die( "Authorization Error\n" );
	}
	//var_dump($result);

	$res=$result->decode_response();
		
	if(!$res['success'])
	{
		echo 'error_code=;'.$res['error_code']."\n";
		die("Authorization failed\n");
	}
	$session_key=$res['sid'];
	
	return $session_key;
}

function client_trassir_health($api,$session_key)
{
	//$result = $api->get("/health", ['username' => $api_username,'password'=>$api_password]);
	$result = $api->get("/health", ['sid' => $session_key]);
	if($result->info->http_code != 200)
	{
		var_dump($result);
		die( "API Call Error\n" );
	}
	//var_dump($result);
	
	$res=$result->decode_response();
	return $res;
}

function client_trassir_get_data($api, $path, $session_key, $dump_result=0)
{
	$result = $api->get($path, ['sid' => $session_key]);
	if($result->info->http_code != 200)
	{
		var_dump($result);
		echo( "API Call Error on path:".$path."\n");
		return null;
	}
	if ($dump_result)
		print_r($result);
		
	$res=$result->decode_response();
	return $res;
}

//function client_trassir($api_url,$api_username,$api_password)
{
	$api = client_api_init($api_url);
	
	$session_key=client_trassir_login($api,$api_username,$api_password);

	if( strlen($session_key)==0)
	{
		die("session_key null length!\n");
	}
	echo "session_key=".$session_key."<br>\n";

	echo '<a href="?dumpcams">dumpcams</a><br>';
	echo '<a href="?channels">channels stats</a><br>';
	echo '<a href="?archive">archive stats</a><br>';
	echo '<a href="?browse=/settings/">settings browse</a><br>';	

	//-----------------------------------------------
	if(isset($_REQUEST['health'])){
		$arg="/health/";
		echo $arg.":\n";
		$data=client_trassir_get_data($api, $arg,$session_key);
		//print_r($data);

		foreach($data as $key => $value)
			echo "$key\t $value<br>\n";
	}
	//-----------------------------------------------
	if(0){
		$arg="/objects/";
		echo $arg.":\n";
		
		$data=client_trassir_get_data($api, $arg, $session_key); 
		//print_r($data);
		
		// loop through the array
		foreach ($data as $row) {
			foreach($row as $key => $value)
				echo "$key:\t $value\t\t";
			echo "\n";
		}
	}
	//-----------------------------------------------
	if(isset($_REQUEST['dumpcams'])){
		//use for dump cameras connections url
		$dir_path="/settings/ip_cameras/";
		$arg=$dir_path;
		echo $arg.":\n";
		
		$data=client_trassir_get_data($api, $arg, $session_key); 
		//print_r($data);
		
		echo "<table>\n";
		// loop through the array
		foreach ($data["subdirs"] as $subdir_guid){
			echo "<tr><td>\n";
			echo $subdir_guid;
			echo "</td><td>\n";
			$arg=$dir_path.$subdir_guid."/name";
			echo "</td><td>\n";
			$data=client_trassir_get_data($api, $arg, $session_key);
			echo $data["value"];
			echo "</td><td>\n";			
			$arg=$dir_path.$subdir_guid."/connection_ip";
			//echo $arg.":\n";
			$data=client_trassir_get_data($api, $arg, $session_key);
			echo $data["value"];
			echo "</td></tr>\n";
		}
		echo "</table>\n";
	}
	//-----------------------------------------------
	if(isset($_REQUEST['browse'])){
		$dir_path=$_REQUEST['browse'];	// check for css vuln
		
		//print urls for current directory level
		$dirs=explode("/", $dir_path);
		$cdir="";
		foreach($dirs as $d){
			echo '<a href="?browse='.$cdir.'">'.$cdir."</a><br>\n";
			$cdir.=$d."/";
		}
		
		$arg=$dir_path;
		$data=client_trassir_get_data($api, $arg, $session_key); 
		
		//if(!isset($data["values"]) && !isset($data["subdirs"]))
			//print_r($data);
		
		if(isset($data["values"])){
			foreach ($data["values"] as $value_name){
				$arg=$dir_path.$value_name;
				$value_data=client_trassir_get_data($api, $arg, $session_key);
				foreach($value_data as $key => $value)
					echo "$key:\t $value\t\t";
				echo "<br>\n";
				//echo $value_name.":\t".$value_data["value"]."\n";
			}
		}
		if(isset($data["subdirs"])){
			foreach ($data["subdirs"] as $value_name){
				$value_name=$value_name."/";
				echo '<a href="?browse='.$dir_path.$value_name.'">'.$dir_path.$value_name."</a><br>\n";
			}		
		}
		exit;
	}
	//-----------------------------------------------
	//"/settings/channels/[GUID_канала]/stats/"
	if(isset($_REQUEST['channels'])){
		$dir_path="/settings/channels/";
		$arg=$dir_path;
		echo $arg.":\n";

		$data=client_trassir_get_data($api, $arg, $session_key); 
		//print_r($data);
		
		// loop through the array
		foreach ($data["subdirs"] as $subdir_guid){
			echo $subdir_guid."\t";
			
			$value_name="name";
			$arg=$dir_path.$subdir_guid."/".$value_name;
			$value_data=client_trassir_get_data($api, $arg, $session_key);
			echo $value_name.":\t".$value_data["value"]."\t";
			
			$arg=$dir_path.$subdir_guid."/stats/";
			client_trassir_dir_dump($api, $arg, $session_key);			
			echo "<br>\n";
		}
	}
	//-----------------------------------------------
	//"/settings/archive/[имя_диска]/stats/"
	if(isset($_REQUEST['archive'])){
		$dir_path="/settings/archive/";
		$arg=$dir_path;
		echo $arg.":\n";

		$data=client_trassir_get_data($api, $arg, $session_key); 
		//print_r($data);
		
		// loop through the array
		foreach ($data["subdirs"] as $subdir_guid){
			echo $subdir_guid."\t";
			
			$value_name="disk_id"; 
			$arg=$dir_path.$subdir_guid."/".$value_name;
			$value_data=client_trassir_get_data($api, $arg, $session_key);
			echo $value_name.":\t".$value_data["value"]."\t";
			
			$value_name="capacity_gb"; 
			$arg=$dir_path.$subdir_guid."/".$value_name;
			$value_data=client_trassir_get_data($api, $arg, $session_key);
			echo $value_name.":\t".$value_data["value"]."\t";
			
			$arg=$dir_path.$subdir_guid."/stats/";
			client_trassir_dir_dump($api, $arg, $session_key);
			echo "<br>\n";
		}
	}
	//-----------------------------------------------	
}

function client_trassir_dir_dump($api, $dir_path, $session_key)
{
	if(empty($api) || empty($dir_path) || empty($session_key))
		return;
	$arg=$dir_path;
	$data=client_trassir_get_data($api, $arg, $session_key); 
	//print_r($data);
	if(!isset($data["values"]))
		return;
	foreach ($data["values"] as $value_name){
		$arg=$dir_path.$value_name;
		$value_data=client_trassir_get_data($api, $arg, $session_key);
		echo $value_name.":\t".$value_data["value"]."\t";
	}	
}

function client_trassir_dir_dump_full($api, $dir_path, $session_key)
{
	if(empty($api) || empty($dir_path) || empty($session_key))
		return;
	$arg=$dir_path;
	$data=client_trassir_get_data($api, $arg, $session_key); 
	//print_r($data);
	if(!isset($data["values"]))
		return;
	foreach ($data["values"] as $value_name){
		$arg=$dir_path.$value_name;
		$value_data=client_trassir_get_data($api, $arg, $session_key);
		foreach($value_data as $key => $value)
			echo "$key:\t $value\t\t";
		echo "\n";
		//echo $value_name.":\t".$value_data["value"]."\n";
	}	
}

function client_trassir_dir_dump_subdirs($api, $dir_path, $session_key)
{
	if(empty($api) || empty($dir_path) || empty($session_key))
		return;
	$arg=$dir_path;
	$data=client_trassir_get_data($api, $arg, $session_key); 
	//print_r($data);
	if(!isset($data["subdirs"]))
		return;
	foreach ($data["subdirs"] as $value_name){
		$value_name=$value_name."/";
		echo '<a href="?browse='.$dir_path.$value_name.'">'.$dir_path.$value_name."</a>\n";
	}		
}

?>