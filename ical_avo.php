<?php

define("_AVOCADO_API_URL_BASE", "https://avocado.io/api/");
define("_AVOCADO_API_URL_LOGIN", _AVOCADO_API_URL_BASE . "authentication/login");
define("_AVOCADO_API_URL_COUPLE", _AVOCADO_API_URL_BASE . "couple");
define("_AVOCADO_COOKIE_NAME", "user_email");
define("_AVOCADO_USER_AGENT", "Avocado Test Api Client v.1.0");

$date = $argv[1];
$military = $argv[2] * 60;
$military_two = $military * 60;
$name = $argv[3];
if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $date)) { 
	$seconds = strtotime($date);
	$minutes_to_add = (($seconds - time()) / 60) + $military;

	$avocadotime =($seconds+ $military_two) * 1000;


	$milliseconds = $avocadotime;
	$two_more_milli = $avocadotime + 12000;
	$two_more = $minutes_to_add + 2;

	$appscript = "<<END
set now to (current date)
	set eStart to now + $minutes_to_add * minutes
	set eEnd to now + $two_more * minutes
	set eName to \" $name \"
	set alarmTime to 0 -- alarm at the exact moment of the event

	tell application \"iCal\"
		set newEvent to make new event at end of events of calendar \"Home\" with properties {summary:eName, start date:eStart, end date:eEnd}
			make new display alarm at end of display alarms of newEvent with properties {trigger interval:alarmTime}
			end tell
END";
}
`osascript $appscript`;


$api = new AvocadoAPI();
$api->updateFromCommandLineInput();
$api->createReminder($milliseconds, $two_more_milli, $name);
class AvocadoAPI {
  var $couple,
      $authManager;

  function AvocadoAPI() {
    $this->authManager = new AvocadoAuthManager();
  }

  function updateFromCommandLineInput() {
    $this->authManager->updateAuthFromCommandLineInput();
    $this->updateCouple();

    # Check that the response from the Avocado API was valid.
    if ($this->couple == null) {
      print "FAILED.  Signature was tested and failed. Try again and check the auth information.\n";
    } else {
      print "SUCCESS.\n\nBelow is your Avocado API signature:\n" .
        $this->authManager->signature . "\n";
    }
  }
  function getCouple(){
//	 $qry_str = "?avosig=".$this->authManager->signature;
	 $ch = curl_init();

	 // Set query data here with the URL
	 curl_setopt($ch, CURLOPT_URL, _AVOCADO_API_URL_BASE."calendar");
//	 $this->signCurlRequest($ch);
	 curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	 curl_setopt($ch, CURLOPT_TIMEOUT, '3');
	 $content = trim(curl_exec($ch));
	 curl_close($ch);
	 $content = json_decode($content);
	 print_r($content);
  }
  function createReminder($milliseconds, $two_more_milli, $name){
//	 $qry_str = "?avosig=".$this->authManager->signature;
	  $ch = curl_init(_AVOCADO_API_URL_BASE."calendar");
	 $fields = array (
		"start" => urlencode($milliseconds),
		"end" => urlencode($two_more_milli),
		"title" => $name,
		"location" => '',
		"description" => $name,
		"timezone" => "America/Chicago"
		);
    curl_setopt($ch, CURLOPT_POSTFIELDS,  $fields);
	curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, _AVOCADO_USER_AGENT);
	$this->signCurlRequest($ch);

 	$returndata = curl_exec ($ch);
    $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

//	 $content = json_decode($content);
//	 print_r($content);
	echo $returndata;
    $this->createdReminder = $response_code == 200 ? json_decode($returndata) : null;
	echo $this->createdReminder;
  }

  
  function updateCouple() {
    # Send the POST request.
    $ch = curl_init(_AVOCADO_API_URL_COUPLE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  //  $this->signCurlRequest($ch);
    $output = curl_exec($ch);
    $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    # Use the HTTP response code to test if a valid API request was made.
    $this->couple = $response_code == 200 ? json_decode($output) : null;
  }

  function signCurlRequest($ch) {
    curl_setopt($ch, CURLOPT_COOKIE, _AVOCADO_COOKIE_NAME . "=" . $this->authManager->cookie);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-AvoSig: " . $this->authManager->signature));
    curl_setopt($ch, CURLOPT_USERAGENT, _AVOCADO_USER_AGENT);
  }
}


class AvocadoAuthManager {
  var $cookie,
      $developer_id,
      $developer_key,
      $email,
      $password,
      $signature;

  function AvocadoAuthManager() {}

  function updateAuthFromCommandLineInput() {
    # Ask the user for all of the necessary authentication info
    $this->email = 'dustycarver@gmail.com';
    $this->password = 'pandabear';
    $this->developer_id = 43;
    $this->developer_key = '2EWP5Rp8vbBxYRFhwZLldd3JPXrZ1HurTM3GUV3yqpPsqmuEnL8lIpI1MxbyzXqT';
    $this->updateSignature();
  }

  function updateSignature() {
    # Get a new cookie by logging into Avocado.
    $this->updateLoginCookie();

    # Hash the user token.
    $hashed_user_token = hash("sha256", $this->cookie . $this->developer_key);

    # Store the new signature.
    $this->signature = $this->developer_id . ":" . $hashed_user_token;
  }

  function updateLoginCookie() {
    $fields = array(
      'email'=>urlencode($this->email),
      'password'=>urlencode($this->password)
    );

    # Send the POST request.
    $ch = curl_init(_AVOCADO_API_URL_LOGIN);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_POST, count($fields));
    curl_setopt($ch, CURLOPT_POSTFIELDS,  get_querystring_from_array($fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, _AVOCADO_USER_AGENT);
    $header = substr(curl_exec($ch), 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    curl_close($ch);

    # Store the cookie for use in later API requests.
    $this->cookie = get_cookie_from_header($header, _AVOCADO_COOKIE_NAME);
  }
}


#-----------------------------------------------------
# Mama's little helpers: functions we needed for this.
#-----------------------------------------------------

function get_querystring_from_array($fields) {
  $fields_string = null;
  foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
  rtrim($fields_string, '&');
  return $fields_string;
}

function get_cookie_from_header($header, $cookie_name) {
  preg_match('/^Set-Cookie: ' . $cookie_name . '=(.*?);/m', $header, $cookie_array);
  return $cookie_array[1];
}

function get_input_silently($msg){
  # NOTE: stty only works on *nix systems.
  system('stty -echo');
  $input = get_input("Password");
  system('stty echo');
  print "\n";
  return $input;
}

function get_input($msg){
  fwrite(STDOUT, "$msg: ");
  return trim(fgets(STDIN));
}

?>
?>
