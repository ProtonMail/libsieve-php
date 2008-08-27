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

<div style="border-style:dashed; border-width:2px; border-color:silver; padding:10px; overflow:auto">
Script to validate:<br/>
<pre><?php
require_once 'lib/libsieve.php';

if (isset($_POST['script'])) {
	print preg_replace_callback(
		'/^/m',
		create_function(
			'$matches',
			'static $line_no = 1;
			return sprintf("<span style=\"background-color:#f5f5f5\">%3d </span>%s", $line_no++, $matches[0]);'
		),
		stripslashes($_POST['script'])
	);

	$text_color = 'green';
	$text = 'success';

	try
	{
		$parser = new Parser();
		$parser->parse(stripslashes($_POST['script']));
	}
	catch (Exception $e)
	{
		$text_color = 'tomato';
		$text = htmlentities($e->getMessage());
	}

	print '</pre><hr size="1"/>Result: <span style="color:'. $text_color .';font-weight:bold">' . $text ."</span>\n";
	print '<hr size="1"/><pre style="font-size:x-small">';
	print htmlentities($parser->dumpParseTree());
}
else {
	print "No script to validate.";
}

?></pre>
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
