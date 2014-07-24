<?php
	session_start();
	include('./lib/lib.pdo.php'); // database class
	include('./lib/jsonrpc.php'); // rpc class

	$debug 			= false;

	$rpcuser 		= "";
	$rpcpass 		= "";
	$rpcip 			= "localhost"; // rpc host
	$rpcport		= 9333; // rpc daemon port
	$blackcoind 	= new jsonRPCClient("http://$rpcuser:$rpcpass@$rpcip:$rpcport");

	$databaseUser 	= "";
	$databasePass 	= "";
	$databaseName 	= "";
	$dbTableName 	= "";
	Db::Connect("mysql:dbname=$databaseName;host=localhost", $databaseUser, $databasePass);

	if($debug)
		echo "<pre>". print_r(GetInfo(), true)."</pre>";
	
	echo "Faucet Address: " . GetAddress() . ".<br>";
	echo "Faucet Balance: " . GetBalance() . ".<br><br>";
	
	$continue = true;
	
	if(isset($_REQUEST["address"])){
		if($_SESSION["my_captcha"] == $_REQUEST["captcha"]){
			$address = $_REQUEST["address"];
			if(strlen($address) == 34){
			$result = GetFromFaucet($address);	
			echo "The faucet has succesfully dispensed {$result["amount"]} to {$address}. <br><br>For reference see transaction: {$result["txid"]}";
			$continue = false;
			} else {
				echo "You have entered an incorrect address";
			}
		} else {
			echo "You have entered an invalid captcha!";
			$continue = true;
		}
	} 
	
	if($continue){	
?>
<form method="post">
	<input type="text" name="address" placeholder="address"/>
	<input type="text" name="captcha" placeholder="captcha"/><img style="padding-top:9px;" src="lib/captcha.php"/>
	<input type="submit"/>
</form>
<?php
	}
	
	function GetInfo() {
		global $blackcoind;
		return $blackcoind->getinfo();
	}
	
	function GetBalance() {
		global $blackcoind;
		$data = $blackcoind->getinfo();
		return $data["balance"];
	}

	function GetAddress(){
		global $blackcoind;
		$data = $blackcoind->listreceivedbyaddress(0, true);
		return $data[0]["address"];
	}

	function WithdrawFrom($to_addr, $amount){
		global $blackcoind;
		$txid = $blackcoind->sendtoaddress($to_addr, floatval($amount));
		return $txid;
	}

	function GetFromFaucet($addr){
		global $dbTableName;
		
		$timelimit 		= 21600; // 6 hours
		$ip 			= $_SERVER["REMOTE_ADDR"]; // requester IP
		$t 				= time();
		$amount 		= mt_rand(50, 150);
		$amount 		= $amount/1000; // amount will be between 0.05 and 0.15 BC
		$data["amount"] = $amount;
		
		$result = Db::Query("SELECT * FROM `$dbTableName` WHERE receiving_addr='$addr' OR receiving_ip='$ip' ORDER BY id DESC LIMIT 1");
		if ($result->count == 0) {
			$result = Db::Query("INSERT INTO `$dbTableName` (receiving_ip, receiving_addr, amount, time) VALUES ('$ip', '$addr', '$amount', '$t')");
			$data["txid"] = WithdrawFrom($addr, $amount);
		} else {
			$time = "";
			$row = Db::Fetch($result);
			$time = $row->time;
			if (($t - $time) > $timelimit) {
				$result = Db::Query("INSERT INTO `$dbTableName` (receiving_ip, receiving_addr, amount, time) VALUES ('$ip', '$addr', '$amount', '$t')");
				$data["txid"] = WithdrawFrom($addr, $amount);
			} else {
				die("Server: Error, you have already used the faucet in the past 6 hours!"); // Already done past hours..
			}
		}
		return $data;
	}
?>