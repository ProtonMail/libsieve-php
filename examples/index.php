<?php

error_reporting (E_ALL | E_STRICT);

include_once '../lib/class.parser.php';

$filename = 'script.siv';
$fd = fopen($filename, 'r');
$script = fread($fd, filesize($filename));
fclose($fd);

$text_color = 'green';
$text = 'success';

try
{
	$parser = new Parser();
	$parser->parse($script);
}
catch (Exception $e)
{
	$text_color = 'tomato';
	$text = $e->getMessage();
	//print "<pre>". $e->getTraceAsString() ."</pre>";
}

print "<small><pre>$script</pre><hr>";
print '<pre style="color:'. $text_color .';font-weight:bold">' . $text .'</pre><hr><pre>';
print htmlentities($parser->dumpParseTree());
print '</pre><hr><pre>';
print $parser->getScriptText();
print '</pre></small>';

?>
