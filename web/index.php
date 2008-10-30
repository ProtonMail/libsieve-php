<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<title>libsieve-php - A PHP Sieve library</title>
</head>
<body style="font-family:sans-serif; font-size:13px">
<div style="width:760px; margin-left:auto; margin-right:auto">

<div style="border-style:dashed; border-width:2px; border-color:silver; padding:10px">
	<h1 style="text-align:center">Welcome to this lousy page</h1>
	<p style="text-align:justify">
		<span style="font-family:monospace">libsieve-php</span> is aiming at being a Sieve
		[<a href="http://tools.ietf.org/html/rfc5228" target="_top">RFC 5228</a>]
		mail filtering language library. It is written in PHP5.
		Once finished you'll be able to manage sieve scripts with it. It currently consists
		of a Sieve script parser that supports some extensions.
	</p>
	<p style="text-align:justify">
		Next step will be to implement support for the
		<a href="http://tools.ietf.org/html/draft-martin-managesieve" target="_top">MANAGESIEVE</a> protocol which will allow you to download
		scripts from, upload them to and manage them on a server like timsieved. When all that is done I will hack up some
		interface classes that will enable you to access and modify parsed scripts without the knowledge of the libraries internals.
	</p>
	<p>
		<span style="font-weight:bold">Current state: ALPHA</span>. <a href="http://sourceforge.net/svn/?group_id=184171" target="_top">Try it</a>,
		but don't expect it to be comfortable or bug free.
	</p>
	<p style="text-align:justify">
		Hack in a Sieve script in the textarea below and try the sieve parser of libsieve-php in action.
		Note that currently the <span style="font-family:monospace">base spec</span> and the extensions
		<span style="font-family:monospace">fileinto, envelope, reject, vacation, subaddress, relational,
		regex, imapflags (draft-03), imap4flags, copy, spamtest, virustest, ereject, editheader, body,
		variables</span>
		and <span style="font-family:monospace">notify</span> are supported by the parser. If you find any
		bugs don't hesitate and <a href="http://sourceforge.net/tracker/?func=add&amp;group_id=184171&amp;atid=908185" target="_top">report
		them via the projects bug tracker</a>. I'm especially happy for reports on malformed scripts getting
		through as well as misleading parser status messages.
	</p>
	
	<form action="validate.php" method="post">
		<p style="text-align:center">
			<textarea name="script" cols="80" rows="25">require ["fileinto"];

if header :is "Sender" "owner-ietf-mta-filters@imc.org"
        {
        fileinto "filter"; # move to "filter" mailbox
        }
elsif address :DOMAIN :is ["From", "To"] "example.com"
        {
        keep;                # keep in "In" mailbox
        }
elsif anyof (NOT address :all :contains
                ["To", "Cc", "Bcc"] "me@example.com",
              header :matches "subject"
                ["*make*money*fast*", "*university*dipl*mas*"])
        {
        fileinto "spam";    # move to "spam" mailbox
        }
else
        {
        fileinto "personal";
        }</textarea><br />
			<input type="submit" value="Validate Sieve Script" />
		</p>
	</form>
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
