<?php
/* @file apns.php
 * @date 2014-04-30
 * @author Tony Florida
 * @brief Send push notification to user's device using APNS

/*
 * @brief send push notification to device
 * @param deviceToken user's device token
 * @param message the push notification message
 */
function apns_send($deviceToken, $message)
{
include "../../frictlist_private/apns_private.php";

$ctx = stream_context_create();
stream_context_set_option($ctx, 'ssl', 'local_cert', $pem_file);
stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);

// Open a connection to the APNS server
$fp = stream_socket_client(
	'ssl://gateway.sandbox.push.apple.com:2195', $err,
	$errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);

if (!$fp)
{
//	exit("Failed to connect: $err $errstr" . PHP_EOL);
	return;
}

//echo 'Connected to APNS' . PHP_EOL;

// Create the payload body
$body['aps'] = array(
	'alert' => $message,
	'sound' => 'default'
	);

// Encode the payload as JSON
$payload = json_encode($body);

// Build the binary notification
$msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;

// Send it to the server
$result = fwrite($fp, $msg, strlen($msg));

/*
if (!$result)
	echo 'Message not delivered' . PHP_EOL;
else
	echo 'Message successfully delivered' . PHP_EOL;
*/

// Close the connection to the server
fclose($fp);

}
?>
