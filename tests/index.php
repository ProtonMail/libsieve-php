<?php

include_once '../lib/class.parser.php';
include_once '../contrib/managesieve.lib.php';

define('MANAGESIEVE_HOST', 'localhost');
define('MANAGESIEVE_USER', 'heiko');
define('MANAGESIEVE_PASS', 'heiko');

print '<table cellpadding="4" style="font-family:sans-serif; border-collapse: collapse">';
print '<tr align="center" style="font-weight:bold;background-color:lightgrey">';
print '  <td style="padding-left:10px">Test</td><td>Expected</td><td>Sieved</td><td style="padding-right:10px">Parser</td>';
print '</tr>';

foreach (array('good', 'bad') as $dir)
{
	$dh = opendir($dir);
	while (($file = readdir($dh)) !== false)
	{
		if (preg_match('/(.+)\.siv$/', $file, $match)) {
			$script = file_get_contents("$dir/$file");

			$sieved = new sieve(MANAGESIEVE_HOST, 2000, MANAGESIEVE_USER, MANAGESIEVE_PASS);
			$sieved->sieve_login();
			if ($sieved->sieve_sendscript("test.siv", $script)) {
				$sieved_bgcolor = $dir == 'good' ? 'lightgreen' : 'tomato';
				$sieved_status = 'good';
				$sieved_error = '';
			}
			else {
				$sieved_bgcolor = $dir == 'bad' ? 'lightgreen' : 'tomato';
				$sieved_status = 'bad';
				$sieved_error = ' title="'. htmlentities($sieved->error_raw[0] .' '. $sieved->error_raw[1]) .'"';
			}

			try {
				$parser = new Parser();
				$parser->parse($script);

				$parser_bgcolor = $dir == 'good' ? 'lightgreen' : 'tomato';
				$parser_status = 'good';
				$parser_error = '';
			}
			catch (Exception $e) {
				$parser_bgcolor = $dir == 'bad' ? 'lightgreen' : 'tomato';
				$parser_status = 'bad';
				$parser_error = ' title="'. htmlentities($e->getMessage()) .'"';
			}

			print '<tr align="center" style="border-style:solid; border-left-style:none; border-right-style:none; border-width:1px">';
			print   '<td align="left" style="padding-left:10px; font-weight:bold">'. $match[1] .'</td><td>'. $dir .'</td>';
			print   '<td><span style="padding:2px; background-color:'. $sieved_bgcolor .'"'. $sieved_error .'>&nbsp;'. $sieved_status .'&nbsp;</span></td>';
			print   '<td style="padding-right:10px"><span style="padding:2px; background-color:'. $parser_bgcolor .'"'. $parser_error .'>&nbsp;'. $parser_status .'&nbsp;</span></td>';
			print '</tr>';
		}
	}
}

print '</table>';

closedir($dh);

?>