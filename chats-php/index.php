<?php

require_once("mysql.php");

$dbHost = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "chats";

$db = new MySQL($dbHost,$dbUsername,$dbPassword,$dbName);

define("FAILED", 6);
define("SUCCESSFUL", 1);

define("SIGN_UP_USERNAME_CRASHED", 2);

define("ADD_NEW_USERNAME_NOT_FOUND", 2);

define("TIME_INTERVAL_FOR_USER_STATUS", 60);
define("USER_APPROVED", 1);
define("USER_UNAPPROVED", 7);


$username = (isset($_REQUEST['username']) && count($_REQUEST['username']) > 0)
							? $_REQUEST['username']
							: NULL;
$password = isset($_REQUEST['password']) ? ($_REQUEST['password']) : NULL;
$port = isset($_REQUEST['port']) ? $_REQUEST['port'] : NULL;
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : NULL;

$db->createDB();

if ($action == "testWebAPI")
{
	if ($db->testconnection()){
	echo SUCCESSFUL;
	exit;
	}else{
	echo FAILED;
	exit;
	}
}
if ($username == NULL || $password == NULL)
{
	echo FAILED;
	exit;
}
$out = NULL;

switch($action)
{

	case "authenticateUser":


		if ($userId = authenticateUser($db, $username, $password))
		{

			$sql = "select u.Id, u.username, (NOW()-u.authenticationTime) as authenticateTimeDifference, u.IP,
										f.providerId, f.requestId, f.status, u.port
							from friends f
							left join users u on
										u.Id = if ( f.providerId = ".$userId.", f.requestId, f.providerId )
							where (f.providerId = ".$userId." and f.status=".USER_APPROVED.")  or
										 f.requestId = ".$userId." ";


			$sqlmessage = "SELECT m.id, m.fromuid, m.touid, m.sentdt, m.read, m.readdt, m.messagetext, u.username from messages m \n"
    . "left join users u on u.Id = m.fromuid WHERE `touid` = ".$userId." AND `read` = 0 LIMIT 0, 30 ";


			if ($result = $db->query($sql))
			{
					$out .= "<data>";
					$out .= "<user userKey='".$userId."' />";
					while ($row = $db->fetchObject($result))
					{
						$status = "offline";
						if (((int)$row->status) == USER_UNAPPROVED)
						{
							$status = "unApproved";
						}
						else if (((int)$row->authenticateTimeDifference) < TIME_INTERVAL_FOR_USER_STATUS)
						{
							$status = "online";

						}
						$out .= "<friend  username = '".$row->username."'  status='".$status."' IP='".$row->IP."' userKey = '".$row->Id."'  port='".$row->port."'/>";


					}
						if ($resultmessage = $db->query($sqlmessage))
							{
							while ($rowmessage = $db->fetchObject($resultmessage))
								{
								$out .= "<message  from='".$rowmessage->username."'  sendt='".$rowmessage->sentdt."' text='".$rowmessage->messagetext."' />";
								$sqlendmsg = "UPDATE `messages` SET `read` = 1, `readdt` = '".DATE("Y-m-d H:i")."' WHERE `messages`.`id` = ".$rowmessage->id.";";
								$db->query($sqlendmsg);
								}
							}
					$out .= "</data>";
			}
			else
			{
				$out = FAILED;
			}
		}
		else
		{
				$out = FAILED;
		}



	break;

	case "signUpUser":
		if (isset($_REQUEST['email']))
		{
			 $email = $_REQUEST['email'];

			 $sql = "select Id from  users
			 				where username = '".$username."' limit 1";



			 if ($result = $db->query($sql))
			 {
			 		if ($db->numRows($result) == 0)
			 		{
			 				$sql = "insert into users(username, password, email)
			 					values ('".$username."', '".$password."', '".$email."') ";


							if ($db->query($sql))
							{
							 		$out = SUCCESSFUL;

							}
							else {
									$out = FAILED;
							}
			 		}
			 		else
			 		{
			 			$out = SIGN_UP_USERNAME_CRASHED;
			 		}
			 }
		}
		else
		{
			$out = FAILED;
		}
	break;

	case "sendMessage":
	if ($userId = authenticateUser($db, $username, $password))
		{
		if (isset($_REQUEST['to']))
		{
			 $tousername = $_REQUEST['to'];
			 $message = $_REQUEST['message'];

			 $sqlto = "select Id from  users where username = '".$tousername."' limit 1";



					if ($resultto = $db->query($sqlto))
					{
						while ($rowto = $db->fetchObject($resultto))
						{
							$uto = $rowto->Id;
						}
						$sql22 = "INSERT INTO `messages` (`fromuid`, `touid`, `sentdt`, `messagetext`) VALUES ('".$userId."', '".$uto."', '".DATE("Y-m-d H:i")."', '".$message."');";

							if ($db->query($sql22))
							{
							 		$out = SUCCESSFUL;
							}
							else {
									$out = FAILED;
							}
						$resultto = NULL;
					}

		$sqlto = NULL;
		}
		}
		else
		{
			$out = FAILED;
		}
	break;

	case "addNewFriend":
		$userId = authenticateUser($db, $username, $password);
		if ($userId != NULL)
		{

			if (isset($_REQUEST['friendUserName']))
			{
				 $friendUserName = $_REQUEST['friendUserName'];

				 $sql = "select Id from users
				 				 where username='".$friendUserName."'
				 				 limit 1";
				 if ($result = $db->query($sql))
				 {
				 		if ($row = $db->fetchObject($result))
				 		{
				 			 $requestId = $row->Id;

				 			 if ($row->Id != $userId)
				 			 {
				 			 		 $sql = "insert into friends(providerId, requestId, status)
				 				  		 values(".$userId.", ".$requestId.", ".USER_UNAPPROVED.")";

									 if ($db->query($sql))
									 {
									 		$out = SUCCESSFUL;
									 }
									 else
									 {
									 		$out = FAILED;
									 }
							}
							else
							{
								$out = FAILED; 
							}
				 		}
				 		else
				 		{
				 			$out = FAILED;
				 		}
				 }
				 else
				 {
				 		$out = FAILED;
				 }
			}
			else
			{
					$out = FAILED;
			}
		}
		else
		{
			$out = FAILED;
		}
	break;

	case "responseOfFriendReqs":
		$userId = authenticateUser($db, $username, $password);
		if ($userId != NULL)
		{
			$sqlApprove = NULL;
			$sqlDiscard = NULL;
			if (isset($_REQUEST['approvedFriends']))
			{
				  $friendNames = split(",", $_REQUEST['approvedFriends']);
				  $friendCount = count($friendNames);
				  $friendNamesQueryPart = NULL;
				  for ($i = 0; $i < $friendCount; $i++)
				  {
				  	if (strlen($friendNames[$i]) > 0)
				  	{
				  		if ($i > 0 )
				  		{
				  			$friendNamesQueryPart .= ",";
				  		}

				  		$friendNamesQueryPart .= "'".$friendNames[$i]."'";

				  	}

				  }
				  if ($friendNamesQueryPart != NULL)
				  {
				  	$sqlApprove = "update friends set status = ".USER_APPROVED."
				  					where requestId = ".$userId." and
				  								providerId in (select Id from users where username in (".$friendNamesQueryPart."));
				  				";
				  }

			}
			if (isset($_REQUEST['discardedFriends']))
			{
					$friendNames = split(",", $_REQUEST['discardedFriends']);
				  $friendCount = count($friendNames);
				  $friendNamesQueryPart = NULL;
				  for ($i = 0; $i < $friendCount; $i++)
				  {
				  	if (strlen($friendNames[$i]) > 0)
				  	{
				  		if ($i > 0 )
				  		{
				  			$friendNamesQueryPart .= ",";
				  		}

				  		$friendNamesQueryPart .= "'".$friendNames[$i]."'";

				  	}
				  }
				  if ($friendNamesQueryPart != NULL)
				  {
				  	$sqlDiscard = "delete from friends
				  						where requestId = ".$userId." and
				  									providerId in (select Id from users where username in (".$friendNamesQueryPart."));
				  							";
				  }
			}
			if (  ($sqlApprove != NULL ? $db->query($sqlApprove) : true) &&
						($sqlDiscard != NULL ? $db->query($sqlDiscard) : true)
			   )
			{
				$out = SUCCESSFUL;
			}
			else
			{
				$out = FAILED;
			}
		}
		else
		{
			$out = FAILED;
		}
	break;

	default:
		$out = FAILED;
		break;
}
echo $out;

function authenticateUser($db, $username, $password){

	$sql22 = "select * from users
					where username = '".$username."' and password = '".$password."'
					limit 1";

	$out = NULL;
	if ($result22 = $db->query($sql22)){
		if ($row22 = $db->fetchObject($result22))
		{
				$out = $row22->Id;

				$sql22 = "update users set authenticationTime = NOW(),
																 IP = '".$_SERVER["REMOTE_ADDR"]."' ,
																 port = 15145
								where Id = ".$row22->Id."
								limit 1";

				$db->query($sql22);


		}
	}

	return $out;
}
?>
