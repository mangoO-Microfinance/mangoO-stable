<?PHP	
	
	//Establish Server & Database Connection
	function connect() {
		include 'mng_settings.php';
		$connect_srv = mysql_connect($db_host, $db_user, $db_passwd);
		if (!$connect_srv) die ('DB connection failed: '.mysql_error());
		$connect_db = mysql_select_db($db_name, $connect_srv);
		if (!$connect_db) die ('Database "'.$db_name.'" cannot be selected: '.mysql_error());
	}

	//Sanitize and secure user input	
	function sanitize($var) {
		if(get_magic_quotes_gpc()) $var = stripslashes($var);
		$var = htmlentities($var);
		$var = strip_tags($var);
		$var = mysql_real_escape_string($var);
		return $var;
	}
	
	//Check for Logon
	function check_logon() {
		$fingerprint = md5($_SERVER['REMOTE_ADDR'].'dh(6Km4$X*'.$_SERVER['HTTP_USER_AGENT']);
		session_start();
		if 	(
					!isset($_SESSION['log_user']) 
					|| 
					$_SESSION['log_fingerprint'] != $fingerprint
				)
				logout();
		session_regenerate_id();
	}
	
	//Check for Administrator Permissions
	function check_admin() {
		if ($_SESSION['log_admin']!=='1'){
			header('Location: start.php');
			die();
		}
	}
	
	//Check for Permission to access Reports
	function check_report() {
		if ($_SESSION['log_report']!=='1'){
			header('Location: start.php');
			die();
		}
	}
	
	//Logout User and destroy his session
	function logout(){
		$_SESSION = array();												//Delete all Session Variables
		if (ini_get("session.use_cookies")) {				//If a Session-Cookie is used, delete it
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 86400, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
		}
		session_destroy();													//Finally, delete the Session
		header('Location: logout_success.php');			//Forward to logout_success.php
		die;
	}	
	
	//Check for CUST_ID
	function check_custid(){
		if (isset($_GET['cust'])) $_SESSION['cust_id'] = sanitize($_GET['cust']);
		else header('Location: start.php');
	}
	
	//Check for LOAN_ID
	function check_loanid(){
		if (isset($_GET['lid'])) $_SESSION['loan_id'] = sanitize($_GET['lid']);
		else header('Location: customer.php?cust='.$_SESSION['cust_id']);
	}
	
	//Check Success for SQL Queries
	function check_sql($sqlquery){
		if (!$sqlquery) die ('SQL-Statement failed: '.mysql_error());
	}
	
	//Include HTML <head>
	function htmlHead($title,$endFlag) {
		echo '<head>
			<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
			<meta http-equiv="Content-Script-Type" content="text/javascript">
			<meta http-equiv="Content-Style-Type" content="text/css">
			<meta name="robots" content="noindex, nofollow">
			<title>mangoO | '.$title.'</title>
			<link rel="stylesheet" type="text/css" href="mangoo.css" />
			<link rel="shortcut icon" href="ico/favicon.ico" type="image/x-icon">';
		if ($endFlag == 1) echo '</head>';
	}
	
	//Include Menu Tabs
	function include_Menu($tab_no){
		
		echo '		
		<!-- MENU HEADER -->
		<div id="menu_header">
			<img src="ico/mangoo_logo_m.png" style="margin: 1em 0 0 .75em;"/>
			<div id="menu_logout">
				<ul>
					<li>'.$_SESSION['log_user'].'
						<ul>
							<li><a href="logout.php">Logout</a></li>
						</ul>
					</li>
				</ul>
			</div>
		</div>';
		
		echo '
		<!-- MENU TABS -->
		<div id="menu_tabs"> 
			<ul>
				<li'; 
				if ($tab_no == 1) echo ' id="tab_selected"';
				echo '><a href="start.php">Start</a></li>
				<li';
				if ($tab_no == 2) echo ' id="tab_selected"';
				echo '><a href="cust_search.php">Customers</a></li>
				<li';
				if ($tab_no == 3) echo ' id="tab_selected"';
				echo '><a href="loan_search.php">Loans</a></li>
				<li';
				if ($tab_no == 4) echo ' id="tab_selected"';
				echo '><a href="books_expense_new.php">Accounting</a></li>';
				
				if ($_SESSION['log_report'] == 1){
					echo '<li';
					if ($tab_no == 5) echo ' id="tab_selected"';
					echo '><a href="rep_incomes.php">Reports</a></li>';
				}
				
				if ($_SESSION['log_admin'] == 1){
					echo '<li';
					if ($tab_no == 6) echo ' id="tab_selected"';
					echo '><a href="set_basic.php">Settings</a></li>';
				}
			echo '</ul>
		</div>';
	}
	
	//Alternating Colors for Table Rows
	function tr_colored(&$row_color) {
		if ($row_color == 0){ 
			echo '<tr>';
			$row_color = 1;
		}
		else {
			echo '<tr class="alt">'; 
			$row_color = 0;
		}
	}
	
	//Calculate Savings Balance
	function sav_balance(){
		$sql_sav = "SELECT * FROM savings, savtype WHERE savings.savtype_id = savtype.savtype_id AND cust_id = '$_SESSION[cust_id]' ORDER BY sav_date, sav_id";
		$query_sav = mysql_query($sql_sav);
		check_sql($query_sav);
		$sav_balance = 0;
		while($row_query_sav = mysql_fetch_assoc($query_sav)){
			$row_sav[] = $row_query_sav;
			$sav_balance = $sav_balance + $row_query_sav['sav_amount'];
		}
		return $sav_balance;
	}
	
	//Error-Message
	function error_alert($text) {
		echo '<script language=javascript>alert(\''.$text.'\')</script>';
	}
	
	//Resizing uploaded images	
	function resize_img($width, $height){
		/* Get original image x y*/		
		list($w, $h) = getimagesize($_FILES['image']['tmp_name']);
		
		/* Calculate new image size with ratio */
		$ratio = max($width/$w, $height/$h);
		$h = ceil($height / $ratio);
		$x = ($w - $width / $ratio) / 2;
		$w = ceil($width / $ratio);
		
		/* New file name */
		$basename = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_FILENAME));
		$path = 'uploads/photos/cust'.$_SESSION['cust_id'].'_'.$width.'x'.$height.'.jpg';
		
		/* Read binary data from image file */
		$imgString = file_get_contents($_FILES['image']['tmp_name']);
		
		/* Create Image from String */
		$image = imagecreatefromstring($imgString);
		$tmp_img = imagecreatetruecolor($width, $height);
		imagecopyresampled(
			$tmp_img, $image,
			0, 0,
			$x, 0,
			$width, $height,
			$w, $h);
		imagejpeg($tmp_img, $path, 95);
		
		return $path;
		
		/* Cleanup Memory */
		imagedestroy($image);
		imagedestroy($tmp_img);
	}
	
	//Get settings from SETTINGS and apply to SESSION variables
	function get_settings(){
		$sql_settings = "SELECT * FROM settings";
		$query_settings = mysql_query($sql_settings);
		check_sql($query_settings);
		while($row_settings = mysql_fetch_assoc($query_settings)){
			
			switch ($row_settings['set_id']){
				case 1:
					$_SESSION['set_msb'] = $row_settings['set_value'];
					break;
				case 2:
					$_SESSION['set_minlp'] = $row_settings['set_value'];
					break;
				case 3:
					$_SESSION['set_maxlp'] = $row_settings['set_value'];
					break;
				case 4:
					$_SESSION['set_cur'] = $row_settings['set_value'];
					break;
				case 5:
					$_SESSION['set_auf'] = $row_settings['set_value'];
					break;
				case 6:
					$_SESSION['set_deact'] = $row_settings['set_value'];
					break;
				case 7:
					$_SESSION['set_dashl'] = $row_settings['set_value'];
					break;
				case 8:
					$_SESSION['set_dashr'] = $row_settings['set_value'];
					break;
			}
		}
	}
	
	//Get current Share Value from SHAREVAL
	function get_sharevalue(){
		$sql_shareval = "SELECT shareval_id, shareval_value FROM shareval WHERE shareval_id IN (SELECT MAX(shareval_id) FROM shareval)";
		$query_shareval = mysql_query($sql_shareval);
		check_sql($query_shareval);
		$result_shareval = mysql_fetch_assoc($query_shareval);
		$_SESSION['share_value'] = $result_shareval['shareval_value'];
	}
	
	//Get Fees from FEES and apply to SESSION variables
	function get_fees(){
		$sql_fees = "SELECT * FROM fees";
		$query_fees = mysql_query($sql_fees);
		check_sql($query_fees);
		while ($row_fees = mysql_fetch_assoc($query_fees)){
			switch ($row_fees['fee_id']){
				case 1:
					$_SESSION['fee_entry'] = $row_fees['fee_value'];
					break;
				case 2:
					$_SESSION['fee_withdraw'] = $row_fees['fee_value'];
					break;
				case 3:
					$_SESSION['fee_stationary'] = $row_fees['fee_value'];
					break;
				case 4:
					$_SESSION['fee_subscr'] = $row_fees['fee_value'];
					break;
				case 5:
					$_SESSION['fee_loan'] = $row_fees['fee_value'];
					break;
				case 6:
					$_SESSION['fee_loanappl'] = $row_fees['fee_value'];
					break;
				case 7:
					$_SESSION['fee_loanfine'] = $row_fees['fee_value'];
					break;
				case 8:
					$_SESSION['fee_loaninterestrate'] = $row_fees['fee_value'];
					break;
			}
		}
	}
		
	//Calculate current customer's savings account balance
	function get_savbalance(){
		$sql_savbal = "SELECT sav_amount FROM savings WHERE cust_id = '$_SESSION[cust_id]'";
		$query_savbal = mysql_query($sql_savbal);
		check_sql($query_savbal);
		
		$sav_balance = 0;
		while($row_savbal = mysql_fetch_assoc($query_savbal)){
			$sav_balance = $sav_balance + $row_savbal['sav_amount'];
		}
		
		return $sav_balance;
	}	
?>