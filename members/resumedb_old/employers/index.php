<?php
session_start(); 
$prepath="..";
include "$prepath/src/database.php";
include "$prepath/src/functions.php";
$self = $_SERVER['PHP_SELF'];

if(!$_SESSION['logged'])
{
	session_defaults();
	$user = new User('e_users');
}
	


if(isset($_POST['submit']))
{
	$user->_checkLogin($_POST['username'],$_POST['password'],isset($_POST['remember'])?1:0);
}	
	
$page_title = "Your Account";

if(isset($_POST['submit']))
{
	if(!$_SESSION['logged'])
	{
		$_SESSION['feedback'] = "Sorry, you've entered the wrong E-mail or Password. <br>If you forgot your password, ".
							"please <a href='reset_password.php'>click here</a>";
	}
}	

site_header();

if($_SESSION['logged'])
{
?>
	<a href='resume_database.php'>Resume Database</a>
<?php
}
else
{
	echo "<form name='login' method='POST' action='$self'>";

	echo "<table><tr>";
	echo "<td>E-mail:</td><td><input type='text' name='username'></td></tr>";
	echo "<tr><td>Password:</td><td><input type='password' name='password'> <input type='submit' name='submit' value='Submit'></td></tr>";
//	echo "<tr><td>Remember Me?</td><td><input type='checkbox' name='remember'>";
	echo "</tr></table></form>";
	echo "<A href='access_request.php'>Click here to request access</a> | <A href='reset_password.php'>Forgot password?</a>";
}

	
	
	include ("$prepath/src/footer.php");	
?>	
