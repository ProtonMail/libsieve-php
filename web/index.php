<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html>
<head>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
	<title>libsieve-php - A PHP Sieve library</title>
</head>
<body>
<form action="validate.php" method="post">
	<h1>Welcome to this lousy page</h1>
	<p>
		Hack in a Sieve script in the textarea below and try the sieve parser of libsieve-php in action.<br/>
		Note that currently only the <span style="font-weight:bold">base spec</span> and extensions <span style="font-weight:bold">vacation, subaddress, relational, comparator-i;ascii-numeric</span> are supported.<br/>
		If you find any bugs don't hesitate and <a href="http://sourceforge.net/tracker/?func=add&group_id=184171&atid=908185">report them via the projects bug tracker</a>.<br/>
		I'm especially happy for reports on malformed scripts getting through and misleading parser status messages.
	</p>
	<textarea name="script" cols="80" rows="25">require "fileinto";
if address :localpart ["To", "CC", "BCC"] ["heiko"] {
	fileinto "INBOX.personal";
}</textarea>
	<p><input type="submit" value="Validate Sieve Script"/></p>
</form>
<hr size="1"/>
<a href="http://sourceforge.net/projects/libsieve-php/">
	<img src="http://sflogo.sourceforge.net/sflogo.php?group_id=184171&amp;type=1" width="88" height="31" border="0" alt="SourceForge.net Logo" />
</a>
<a href="http://validator.w3.org/check?uri=referer">
	<img src="http://www.w3.org/Icons/valid-xhtml10" alt="Valid XHTML 1.0 Transitional" height="31" width="88" border="0" />
</a>
</body>
</html>
