<?php
//  SET TIMEZONE
header('Content-type: application/javascript');
date_default_timezone_set('Europe/Tallinn');

$offset = 0;
$jobsconfirmed = 0;

//  CREATE NEW DATE VARIABLES                                                                          
$currenttime = date('H:i:s');
$currentday = date('d/M/y');
$currenthour = date('G');
$currentminute = date('i');
$currentsec = date('s');

//  CHECK IF JOB TIME AND COURSE TIME OVERLAPS
function checkverdict() {
	//  ARRAY OF DATES TO BE AVOIDED IF PRESENT/AWAY
	$course = array(
					array(
					    'startdate' => "2013-06-03",
					    'beginhour' => 16,
					    'endhour' => 23),
					array(
					    'startdate' => "2013-06-08",
					    'beginhour' => 0,
					    'endhour' => 23),
					array(
					    'startdate' => "2013-06-15",
					    'beginhour' => 17,
					    'endhour' => 19));

	if($_GET['presense'] == "home") {
		$course[] = array(
						'startdate' => date("Y-m-d"),
						'beginhour' => (int)$currenthour,
						'endhour' => (int)$currenthour+1);
	} elseif($_GET['presense'] == "away") {
		$course[] = array(
						'startdate' => date("Y-m-d"),
						'beginhour' => (int)$currenthour,
						'endhour' => 23);
	}
	//  DEFAULT ANSWER
	$verdict = 'confirm';

	//  CHECK IF WORK TIME FALLS INTO RESTRICTED CATEGORY
	if(isset($course)) {
		global $begindate, $begintime, $endtime, $duration;
		$jobendtime = ((int)$endtime < 7 && (int)$begintime > 7) ? 24 + (int)$endtime : (int)$endtime;
		foreach ($course as $value) {
			if ($value['startdate'] === $begindate) {
				if ($jobendtime >= $value['beginhour'] && $jobendtime <= $value['endhour']) {
					$verdict = 'deny';
				} elseif ((int)$begintime >= $value['beginhour'] && (int)$begintime <= $value['endhour']) {
					$verdict = 'deny';
				} elseif ($value['beginhour'] >= (int)$begintime && $value['endhour'] >= (int)$begintime && $value['beginhour'] <= $jobendtime && $value['endhour'] <= $jobendtime) {
					$verdict = 'deny';
				}
			}
		}
	}
	return $verdict;
}

//  REPORT STATUS BACK TO HTML VIA JSONP
function reportstatus($status) {  
	global $currentday, $currenttime, $currenthour, $currentminute, $currentsec, $numberofchars;
	$logline = "\n".$currentday." ".$currenttime." - ".$numberofchars." - ".$status.", Connect OK.";
	echo $_GET['callback'].'('.json_encode(array(
												'status'        => $status,
												'currentday'    => $currentday, 
												'currenttime'   => $currenttime,
												'currenthour'   => $currenthour, 
												'currentminute' => $currentminute, 
												'currentsec'    => $currentsec, 
												'numberofchars' => $numberofchars)).');';
}            

// SEND DATA TO LOG TO SAVE
function postlog() {
	global $chu, $currentday, $currenttime, $numberofchars, $result, $jobname, $confirmcode, $begindate, $begintime, $endtime, $duration, $numberofjobs;
	curl_setopt ($chu, CURLOPT_POST, true);
	curl_setopt ($chu, CURLOPT_POSTFIELDS, json_encode(array(
															'code'          => $confirmcode,
															'dateconfirmed' => $currentday,
															'timeconfirmed' => $currenttime,
															'type'          => $jobname,
															'size'          => $numberofchars,
															'result'        => $result,
															'taskdate'      => $begindate,
															'startitime'    => $begintime,
															'endtime'       => $endtime,
															'duration'      => $duration,
															'numberofjobs'  => $numberofjobs)));
	curl_setopt ($chu, CURLOPT_RETURNTRANSFER, true);
	curl_setopt ($chu, CURLOPT_URL, 'http://betgeniuslog.site90.net/savexml.php'); 
	return curl_exec($chu);
}

//  INITIALIZE cURL BETGENIUS REQUEST AND DEFINE PARAMETERS
function get($url, $urlreferrer) {
	global $ch;
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: text/html, application/xhtml+xml, */*'));
	curl_setopt($ch, CURLOPT_REFERER, $urlreferrer);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept-Language: en-us'));
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.0; Trident/5.0)');
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
	curl_setopt($ch, CURLOPT_URL, $url); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt'); 
	curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
	return curl_exec($ch);
}

//  -----------------------------------  MAIN PROGRAM STARTS  ------------------------------------------

$ch = curl_init();
 
//  FETCH PAGE AND MEASURE ITS SIZE
$content = get('http://task.betgenius.com/WorkAvailable', 'http://task.betgenius.com');
$numberofchars = strlen($content);
 
//  IF SIZE IS NOT 2399, RUN THE CYCLE TO CONFIRM JOB
if ($numberofchars != 2399 && $numberofchars > 2300 && $numberofchars < 6000) {
	do {
        //  INITIALIZE cURL LOG REQUEST 
        $chu = curl_init();

        //  RIP USEFUL DATA, INCLUDING CONFIRMCODE
        $numberofjobs = substr_count($content, '/WorkAvailable/Accept/');
        $jobpos = strpos($content, '/WorkAvailable/Accept/', $offset);
        if ($jobpos === false) {
			curl_close($ch);
            curl_close($chu);
            reportstatus('Unavailable job(s)');
            exit;
        }
        $confirmcode = substr($content, $jobpos+22, 5);
        //  FIND OUT WHAT TYPE OF JOB ARE WE DEALING WITH
        switch (substr($content, $jobpos+104, 8)) {
            case 'Football':
				switch (substr($content, $jobpos+104, 11)) {
					case 'Football In':
						$jobname = 'Football InRunning';
						$jobnamelength = 18;
						break;
					case 'Football Me':
						$jobname = 'Football Media';
						$jobnamelength = 14;
						break;
					case 'Football Mi':
						$jobname = 'Football MiniMedia';
						$jobnamelength = 18;
						break;
				}
				break;
		    case 'Resource':
                $jobname = 'Resource';
                $jobnamelength = 8;
                break;
            case 'Resultin':
                $jobname = 'Resulting';
                $jobnamelength = 9;
                break;
            case 'UAT Test':
                $jobname = 'UAT Testing';
                $jobnamelength = 11;
                break;
            case 'IR Train':
                $jobname = 'IR Training';
                $jobnamelength = 11;
                break;
            case 'Media Tr':
                $jobname = 'Media Training';
                $jobnamelength = 14;
                break;
            default:
                $jobname = substr($content, $jobpos+104, 8);
                $jobnamelength = strlen($jobname);
        }
        $begindate = substr($content, $jobpos+104+$jobnamelength+66, 10);
        $begintime = substr($content, $jobpos+104+$jobnamelength+66+11, 5);
        $endtime = substr($content, $jobpos+104+$jobnamelength+66+16+55+11, 5);
        $duration = substr($content, $jobpos+104+$jobnamelength+66+16+55+16+55, 6);
        $beginhour = (int)substr($begintime, 0, 2);
        if (substr($duration, 5, 6) != 'm') {
	        $duration = $duration.'m';
        }

        //  AVOID CONFIRMING NIGHT JOB (FROM 00 TO 8), OTHERWISE CONFIRM THE JOB AND DEFINE RESPONSE
        if (($beginhour >= 7 && ($jobnamelength === 18 || $jobnamelength === 14) && (int)$duration === 2) || ($beginhour >= 7 && $jobnamelength != 18 && $jobnamelength != 14 && (int)$duration >= 0)) {
    	    if (checkverdict() === 'confirm') {
    	    	
    	    	//  CONFIRM JOB
				$confirmcontent = get('http://task.betgenius.com/WorkAvailable/Accept/'.$confirmcode, 'http://task.betgenius.com/WorkAvailable');
				
				//  CHECK CONFIRMATION
				$checkifconfirmed = get('http://task.betgenius.com/', 'http://task.betgenius.com/WorkAvailable');
				$beginpos = strpos($checkifconfirmed, '<th colspan="5">');
				$endpos = strpos($checkifconfirmed, '<a href="/Home/');
				$checkchunk = substr($checkifconfirmed, $beginpos, $endpos-$beginpos);
				$haystack = preg_replace('/\s+/', '', $checkchunk);

				$preneedle = "<tr><td><p>Callproductionmanagertocancelthistask</p></td><tdstyle=\"text-align:center;\"></td><td>{$jobname}</td><td>{$begintime}</td><td>{$endtime}</td><td>{$duration}</td></tr>";
				$needle = preg_replace('/\s+/', '', $preneedle);

				if (strlen($confirmcontent) == 131 && (strpos($checkifconfirmed, $confirmcode) !== false || strpos($haystack, $needle) !== false)) { 
					$result = 'Successfully confirmed';
					$jobsconfirmed++;  
				} else {                                                                                          
					$result = 'Failed to confirm';                                                                    
				}

				//  SAVE RESULTS TO LOG FILE  
				$postcontent = postlog(); 
				curl_close($chu);
			} else {
				$result = 'Refused. Special schedule';
				//  SAVE RESULTS TO LOG FILE 
				$checkiflogged = file_get_contents('http://betgeniuslog.site90.net/log.html');
				if (strpos($checkiflogged, $confirmcode) === false) {
					$postcontent = postlog();  
					curl_close($chu);
				}
				$offset = $jobpos+70;
			}
		} else {
			if ($beginhour < 7) {
        		$result = 'Refused. Night job';
        	} elseif ($beginhour >= 7 && ($jobnamelength === 18 || $jobnamelength === 14) && (int)$duration !== 2) {
				$result = 'Refused. False duration';
        	} elseif ($beginhour >= 7 && $jobnamelength != 18 && $jobnamelength != 18 && (int)$duration < 0) {
				$result = 'Refused. Negative duration';
            }
            //  SAVE RESULTS TO LOG FILE 
        	$checkiflogged = file_get_contents('http://betgeniuslog.site90.net/log.html');
        	if (strpos($checkiflogged, $confirmcode) === false) {
				$postcontent = postlog();  
				curl_close($chu);
        	}
    		$offset = $jobpos+70;
		}

        //  CHECK IF ANY JOB IS LEFT UNCONFIRMED                
        $content = get('http://task.betgenius.com/WorkAvailable', 'http://task.betgenius.com');                 
        $numberofchars = strlen($content);                                                                                                         
    } while($numberofchars != 2399 && $numberofchars != 0 && $numberofchars != 7008 && $numberofchars != 326 && $numberofchars != 158 && $numberofchars < 8000);

    curl_close($ch);
    reportstatus($jobsconfirmed.' job(s) confirmed');
	exit;

} elseif (($numberofchars < 2000 && $numberofchars != 158) || $numberofchars > 6000) {
    curl_close($ch); 
    reportstatus('ASP.net error');
    exit;
} elseif ($numberofchars == 2399) {
    curl_close($ch);
    reportstatus('No change');
    exit;
} elseif ($numberofchars == 158) {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_HEADER, 0);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
	curl_setopt($curl, CURLOPT_COOKIEFILE, "cookie.txt");
	curl_setopt($curl, CURLOPT_COOKIEJAR, "cookie.txt");
	curl_setopt($curl, CURLOPT_URL, "http://task.betgenius.com/Account/LogOn"); 
	curl_setopt($curl, CURLOPT_POSTFIELDS, "UserName=porsm10@gmail.com&Password=password");
	curl_exec($curl);
	
	curl_close($curl);
	curl_close($ch); 
	reportstatus('Cookie set');
	exit;
}
?>		