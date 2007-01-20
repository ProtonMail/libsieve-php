<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html>
<head>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
	<title>libsieve-php - A PHP Sieve library</title>
</head>
<body>
[<a href="javascript:history.back()">back</a>] [<a href="index.php">home</a>]<hr size="1"/>
Script to validate:<br/>
<pre><?php
require_once 'lib/libsieve.php';

if (isset($_POST['script'])) {
	print stripslashes($_POST['script']);

	$parser = new Parser();
	$ret = $parser->parse(stripslashes($_POST['script']));

	print '</pre><hr size="1"/>Result: <span style="color:'. ($ret ? 'green': 'tomato') .';font-weight:bold">' . htmlentities($parser->status_text) ."</span>\n";
	print '<hr size="1"/><pre style="font-size:x-small">';
	print htmlentities($parser->tree_->dump());
}
else {
	print "No script to validate.";
}

?></pre>
<hr size="1"/>
<a href="http://sourceforge.net/projects/libsieve-php/">
	<img src="http://sflogo.sourceforge.net/sflogo.php?group_id=184171&amp;type=1" width="88" height="31" border="0" alt="SourceForge.net Logo" />
</a>
<a href="http://validator.w3.org/check?uri=referer">
	<img src="http://www.w3.org/Icons/valid-xhtml10" alt="Valid XHTML 1.0 Transitional" height="31" width="88" />
</a>
</body>
</html>
