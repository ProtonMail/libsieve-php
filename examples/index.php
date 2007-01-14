<?php

include_once 'lib/class.parser.php';

$filename = 'script.siv';
$fd = fopen($filename, 'r');
$script = fread($fd, filesize($filename));
fclose($fd);

$parser = new Parser();
$ret = $parser->parse($script);

print "<small><pre>$script</pre><hr>";
print '<pre style="color:'. ($ret ? 'green': 'tomato') .';font-weight:bold">' . $parser->status_text .'</pre><hr><pre>';
print htmlentities($parser->dumpParseTree());
print '</pre></small>';

?>
