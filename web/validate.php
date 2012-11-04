<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<title>libsieve-php - A PHP Sieve library</title>
</head>

<body style="font-family:sans-serif; font-size:13px">
<div style="width:760px; margin-left:auto; margin-right:auto">

<p>
	[<a href="javascript:history.back()">back</a>] [<a href="index.php">home</a>]
</p>

<div style="border:2px dashed silver; padding:10px; overflow:auto">
<?php
require_once 'lib/libsieve.php';

function print_line($matches)
{
	static $line_no = 1;
	global $error_line;

	if ($line_no == $error_line) {
		$bgcolor = '#f5c5c5';
		$style2 = ' style="background-color:#f5d5d5"';
	}
	else {
		$bgcolor = '#f5f5f5';
		$style2 = '';
	}

	print '<tr><td style="text-align:right; background-color:'. $bgcolor .'">'. $line_no++ .'&nbsp;</td>' .
		'<td'. $style2 .'>'. rtrim($matches[0]) ."</td></tr>\n";
}

if (isset($_POST['script']))
{
	try {
		$parser = new Parser();
		$parser->parse($_POST['script']);

		$text_color = 'green';
		$text = 'success';
		$error_line = 0;
	}
	catch (SieveException $e) {
		$text_color = 'black';
		$text = htmlentities($e->getMessage());
		$error_line = $e->getLineNo();
	}

	print "Script to validate:<br />\n";
	print '<table cellpadding="0" style="margin:5px 0px 10px 0px; width:100%; border-spacing:0px; font:13px monospace; border:2px solid #f5f5f5">'."\n";
	print preg_replace_callback("/^(.*)(\n|$)/Um", "print_line",
		strtr($_POST['script'], array(
			' ' => '&nbsp;',
			"\t" => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
		))
	);
	print "</table>\n";

	print 'Result: <span style="color:'. $text_color .'; font-weight:bold">' . $text ."</span>\n</div><br />\n\n";
	print '<div style="border:2px dashed silver; padding:10px; overflow:auto">'."\n".'<pre style="font-size:x-small">';
	print htmlentities($parser->dumpParseTree()) . "\n</pre>\n";
}
else {
	print "No script to validate.";
}

?>
</div>

<p>
<a href="http://sourceforge.net/projects/libsieve-php/" target="_top">
	<img src="http://sflogo.sourceforge.net/sflogo.php?group_id=184171&amp;type=1"
	     width="88" height="31" border="0" alt="SourceForge.net Logo" /></a>
<a href="http://validator.w3.org/check?uri=referer" target="_top">
	<img src="http://www.w3.org/Icons/valid-xhtml10" height="31" width="88"
	     border="0" alt="Valid XHTML 1.0 Transitional" /></a>
</p>
</div>
</body>
</html>
