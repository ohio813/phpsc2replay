<?php
/*
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
?>

<html>
<body>
<p><b>NOTE: this test page can only parse replays from SC2 beta phase 2</b><br />
Expect gazillion error messages if you try an older replay file.</p>
<form enctype="multipart/form-data" action="upload_file.php" method="POST">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $MAX_FILE_SIZE;?>" />
    Choose file to upload: <input name="userfile" type="file" /><br />
	<label for="debug">Debug?</label><input type="checkbox" name="debug" value="1" /><br />
	<br /><br />
    <input type="submit" value="Upload File" />
</form>
<?php
function createAPMImage($vals, $length, $fn) {
	$width = 300;
	$height = 200;
	$pixelsPerSecond = $width/ $length;
	$pic = imagecreatetruecolor($width,$height);
	$lineColor = imagecolorallocate($pic,0,0,0);
	$lineColorGrey = imagecolorallocate($pic,192,192,192);
	$bgColor = imagecolorallocate($pic,255,255,255);
	$bgColorT = imagecolorallocatealpha($pic,255,255,255,127);
	imagefill($pic,0,0,$bgColorT);
	// first create x/y pairs
	// do this by adding up the actions of the 60 seconds before the pixel
	// if there are less than 60 seconds, extrapolate by multiplying with 60/$secs
	// the time index corresponding to a pixel can be calculated using the $pixelsPerSecond variable,
	// it should always be 0 < $pixelsPerSecond < 1
	$xypair = array();
	$maxapm = 0;
	for ($x = 1;$x <= $width;$x++) {
		$secs = ceil($x / $pixelsPerSecond);
		$apm = 0;
		if ($secs < 60) {
			for ($tmp = 0;$tmp < $secs;$tmp++)
				$apm += $vals[$tmp];
			$apm = $apm / $secs * 60;
		} else {
			for ($tmp = $secs - 60;$tmp < $secs;$tmp++)
				$apm += $vals[$tmp];
			$apm = $apm;
		}
		if ($apm > $maxapm)
			$maxapm = $apm;
		$xypair[$x] = $apm;

	}

	// draw the pixels
	if ($maxapm <= 0)
		return;
	for ($i = 2;$i <= $width;$i++) {
		imageline($pic,$i - 1,$height - $xypair[$i - 1] / $maxapm * $height, $i,$height - $xypair[$i] / $maxapm * $height,$lineColor);
	}
	// build a seperate container image 
	$frame = imagecreatetruecolor($width +50,$height+50);
	imagefill($frame,0,0,$bgColor);
	
	imagerectangle($frame,40,0,$width + 40,$height,$lineColor);
	imageline($frame,40,$height / 2,$width + 40,$height / 2, $lineColorGrey);


	imagestringup($frame,4,5,$height - 15,"APM -->",$lineColor);
	imagestring($frame,4,55,$height + 20,"Time (minutes)",$lineColor);
	imagestring($frame,2,25,$height - 15,"0",$lineColor);
	imagestring($frame,2,20,($height / 2),floor($maxapm / 2),$lineColor);
	imagestring($frame,2,20,0,floor($maxapm),$lineColor);
	$lengthMins = ($length / 60);
	for ($i = 0;$i < $lengthMins;$i+=5) {
		imagestring($frame,2,40+($width / ($lengthMins / 5) * ($i / 5)),$height + 5,$i,$lineColor);
		if ($i > 0)
			imageline($frame,40+($width / ($lengthMins / 5) * ($i / 5)),0,40+($width / ($lengthMins / 5) * ($i / 5)),$height, $lineColorGrey);		
	}
	// copy the graph onto the container image and save it
	imagecopy($frame,$pic,40,0,0,0,$width,$height);
	imagepng($frame,$fn);
	imagedestroy($frame);
	imagedestroy($pic);
}


$MAX_FILE_SIZE = 1000000;
if (isset($_FILES['userfile'])) {
	$error = $_FILES['userfile']['error'];
	$type = $_FILES['userfile']['type'];
	$name = $_FILES['userfile']['name'];
	$tmpname = $_FILES['userfile']['tmp_name'];
	$size = $_FILES['userfile']['size'];
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
		if (class_exists("MPQFile") || (include 'mpqfile.php')) {
			$start = microtime_float();
			if ($_POST['debug'] == 1) {
				echo sprintf("<b>Debugging is on.</b><br />\n");
			}
			$a = new MPQFile($tmpname,true,(($_POST['debug'] == 1)?2:0));
			$init = $a->getState();

			if ($init == MPQ_ERR_NOTMPQFILE)
				echo "Error parsing uploaded file, make sure it is a valid MPQ archive!<br />\n";
			else if ($a->getVersion() < 9)
				echo "Error: This parser only supports SC2 beta demos from major version 9 onwards<br />\n";
			else {
				echo sprintf("Major version %d, build %d<br />\n",$a->getVersion(),$a->getBuild());
				$b = $a->parseReplay();
				$tmp = $b->getPlayers();
				echo sprintf("Map name: %s, Game length: %s<br />\n",$b->getMapName(),$b->getFormattedGameLength());
				echo sprintf("Team size: %s, Game speed: %s<br />\n",$b->getTeamSize(), $b->getGameSpeedText());
				
				$apmString = "<b>APM graphs</b><br />\n";
				echo "<table border=\"1\"><tr><th>Player name</th><th>Race</th><th>Color</th><th>Team</th><th>Average APM<br />(experimental)</th><th>Winner?</th></tr>\n";
				foreach($tmp as $value) {
					$wincolor = ($value['won'] == 1)?0x00FF00:0xFF0000;
					echo sprintf("<tr><td>%s</td><td>%s</td><td><font color=\"#%s\">%s</font></td><td>%s</td><td style=\"text-align: center\">%d</td><td style=\"background-color: #%06X; text-align: center\">%d</td></tr>\n",
									$value['sName'],
									$value['race'],
									$value['color'],
									$value['sColor'],
									($value['party'] > 0)?"Team ".$value['party']:"-",
									($value['party'] > 0)?(round($value['apmtotal'] / ($b->getGameLength() / 60))):0,
									((isset($value['won']))?$wincolor:0xFFFFFF),
									(isset($value['won']))?$value['won']:(($value['party'] > 0)?"Unknown":"-")
								);
					if ($value['party'] > 0) {
						$apmFileName = $value['id']."_".md5($name).".png";
						createAPMImage($value['apm'],$b->getGameLength(),$apmFileName);
						$apmString .= sprintf("%s:<br /><img src=\"$apmFileName\" /><br />\n",$value['sName']);
					}
				}
				echo "</table><br />";
				$messages = $b->getMessages();
				if (count($messages) > 0) {
					echo "<b>Messages:</b><br /><table border=\"1\"><tr><th>Time</th><th>Player</th><th>Target</th><th>Message</th></tr>\n";
					foreach ($messages as $val)
						echo sprintf("<tr><td>%d sec</td><td>%s</td><td>%s</td><td>%s</td></tr>\n",$val['time'],
									  $val['name'], ($val['target'] == 2)?"Alliance":"All",$val['message']);
					echo "</table><br />\n";
				}
				echo $apmString;
				
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
						echo sprintf("<tr><td>%d sec</td>",$value['t'] / 16);
						foreach ($tmp as $value2) {
							if ($value2['party'] == 0) continue;
							if ($value['p'] == $value2['id'])
								echo sprintf("<td>%s</td>",$b->getAbilityString($value['a']));
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
}
?>

</body>
</html>