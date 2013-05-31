<?php
$postData = json_decode(file_get_contents("php://input"), true);

$doc = new DOMDocument("1.0", "UTF-8");

// Setting formatOutput to true will turn on xml formating so it looks nicely
// however if you load an already made xml you need to strip blank nodes if you want this to work
$doc->load('tasks.xml', LIBXML_NOBLANKS);
$doc->formatOutput = true;

// Count numebr of tasks with this confirm code, log if there are none
$xpath = new DOMXpath($doc);
$elementsArraylength = $xpath->query("/tasks/task[code = '".$postData['code']."' and dateconfirmed = '".$postData['dateconfirmed']."']")->length;
if ($elementsArraylength == 0) {
  // Count number of "task" nodes
	$number = $doc->getElementsByTagName('task')->length;

	// Get the root element "tasks"
	$tasks = $doc->documentElement;

	// Create new task element
	$task = $doc->createElement("task");

		if ($postData["result"] == "Successfully confirmed") {
			$taskAttribute = $doc->createAttribute('highlight');
			$taskAttribute->value = "green";
			$task->appendChild($taskAttribute);
		} else {
			$taskAttribute = $doc->createAttribute('highlight');
			$taskAttribute->value = "red";
			$task->appendChild($taskAttribute);
		}

		// Create and add new elements to task element
		$tasknumber = $doc->createElement("tasknumber", $number+1);
		$task->appendChild($tasknumber);	
		$dateconfirmed = $doc->createElement("dateconfirmed", $postData["dateconfirmed"]);
		$task->appendChild($dateconfirmed);
		$timeconfirmed = $doc->createElement("timeconfirmed", $postData["timeconfirmed"]);
		$task->appendChild($timeconfirmed);
		$size = $doc->createElement("size", $postData["size"]);
		$task->appendChild($size);
		$result = $doc->createElement("result", $postData["result"]);
		$task->appendChild($result);
		$type = $doc->createElement("type", $postData["type"]);
		$task->appendChild($type);
		$code = $doc->createElement("code", $postData["code"]);
		$task->appendChild($code);
		$taskdate = $doc->createElement("taskdate", $postData["taskdate"]);
		$task->appendChild($taskdate);
		$starttime = $doc->createElement("starttime", $postData["starttime"]);
		$task->appendChild($starttime);
		$endtime = $doc->createElement("endtime", $postData["endtime"]);
		$task->appendChild($endtime);
		$duration = $doc->createElement("duration", $postData["duration"]);
		$task->appendChild($duration);
		$numberofjobs = $doc->createElement("numberofjobs", $postData["numberofjobs"]);
		$task->appendChild($numberofjobs);

	// Append new task to tasks element
	$tasks->appendChild($task);

	// Save changes
	$doc->save('tasks.xml');
}
?>
