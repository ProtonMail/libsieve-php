<?php

require_once('../class.extensions.php');

class RegexExtension extends SieveExtension
{
	function requireString()
	{
		return 'regex';
	}

	function registerMatchTypes()
	{
		return 'regex';
	}
}

$extensionRegistry->add(new RegexExtension());

?>