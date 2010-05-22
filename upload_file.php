<html>
<body>
<form enctype="multipart/form-data" action="upload_file.php" method="POST">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $MAX_FILE_SIZE;?>" />
    Choose file to upload: <input name="userfile" type="file" /><br />
	<label for="debug">Debug?</label><input type="checkbox" name="debug" value="1" /><br />
	<br /><br />
    <input type="submit" value="Upload File" />
</form>
<?php
$MAX_FILE_SIZE = 1000000;
if (isset($_FILES['userfile'])) {
$error = $_FILES['userfile']['error'];
$type = $_FILES['userfile']['type'];
$name = $_FILES['userfile']['name'];
$tmpname = $_FILES['userfile']['tmp_name'];
$size = $_FILES['userfile']['size'];
$uploaddir = '/opt/lampp/htdocs/ooaemkee/SC2Replay/upload/';
$err = false;
if ($size >= $MAX_FILE_SIZE) {
	echo "Error: The uploaded file was too large. The maximum size is ".$MAX_FILE_SIZE." bytes.<br />";
	$err = true;
}
if ($error == UPLOAD_ERR_PARTIAL) {
	echo "Error: The upload was not completed successfully. Please try again.<br />";
	$err = true;
}
if ($error == UPLOAD_ERR_NO_FILE) {
	echo "Error: No file was selected for uploading.<br />";
	$err = true;
}
if (!is_uploaded_file($tmpname)) {
	echo "Error: Uploaded filename doesn't point to an uploaded file.<br />";
	$err = true;
}
if ($err !== true) {
	if (class_exists("MPQFile") || (include 'mpqread.php')) {
		$start = microtime_float();
		$a = new MPQFile($tmpname,true,($_POST['debug'] == 1)?"true":false);
		$init = $a->getState();

		echo sprintf("Major version %d, build %d<br />\n",$a->getVersion(),$a->getBuild());
		if ($init == MPQ_ERR_NOTMPQFILE)
			echo "Error parsing uploaded file, make sure it is a valid MPQ archive!<br />\n";
		else if ($a->getVersion() < 9)
			echo "Error: This parser only supports SC2 beta demos from major version 9 onwards<br />\n";
		else {
			$b = new SC2Replay();
			if ($_POST['debug'] == 1) {
				echo sprintf("<b>Debugging is on.</b><br />\n");
				$b->setDebug(true);
			}
			$b->parseReplay($a);
			$tmp = $b->getPlayers();
			echo sprintf("Map name: %s, Game length: %s<br />\n",$b->getMapName(),$b->getFormattedGameLength());
			echo sprintf("Team size: %s, Game speed: %s<br />\n",$b->getTeamSize(), $b->getGameSpeedText());
			
			echo "<table><tr><th>Player name</th><th>Long name</th><th>Race</th><th>Color</th><th>Team</th><th>Winner?</th></tr>\n";
			foreach($tmp as $value) {
				$wincolor = ($value['won'] == 1)?0x00FF00:0xFF0000;
				echo sprintf("<tr><td>%s</td><td>%s</td><td>%s</td><td><font color=\"#%s\">%s</font></td><td>%s</td><td style=\"background-color: #%06X; text-align: center\">%d</td></tr>\n",
								$value['sName'],$value['lName'],$value['race'],$value['color'],$value['sColor'],(($value['party'] > 0)?"Team ".$value['party']:"-"),((isset($value['won']))?$wincolor:0xFFFFFF),(isset($value['won']))?$value['won']:(($value['party'] > 0)?"Unknown":"-"));
			}
			echo "</table><br />";
			
			$t = $b->getEvents();
			if (isset($sc2_abilityCodes) || (include 'abilitycodes.php')) {
				echo "<table border=\"1\"><tr><th>Timecode</th>\n";
				$pNum = count($tmp);
				foreach ($tmp as $value) {
				  if ($value['party'] > 0)
					echo sprintf("<th>%s (%s)</th>",$value['sName'],$value['race']);
				}
				echo "</tr>\n";
				foreach ($t as $value) {
					echo sprintf("<tr><td>%d sec</td>",$value['t'] / 64);
					foreach ($tmp as $value2) {
						if ($value2['party'] == 0) continue;
						if ($value['p'] == $value2['id'])
							echo sprintf("<td>%s</td>",$sc2_abilityCodes[$value['a']]);
						else
							echo "<td></td>";
					}
					echo "</tr>\n";
				}
				echo "</table>";
			}
		}
		$end =  microtime_float();
		echo sprintf("Time to parse: %d ms.<br />\n",(($end - $start)*1000));
	}
}
?>

</body>
</html>