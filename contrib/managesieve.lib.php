<?php
/**
 * sieve-php.lib.php
 *
 * $Id: managesieve.lib.php,v 1.11 2007/01/17 13:46:10 avel Exp $ 
 *
 * Copyright 2001-2003 Dan Ellis <danellis@rushmore.com>
 *
 * This program is released under the GNU Public License.  See the enclosed
 * file COPYING for license information. If you did not receive this file, see
 * http://www.fsf.org/copyleft/gpl.html.
 *
 * You should have received a copy of the GNU Public License along with this
 * package; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place - Suite 330, Boston, MA 02111-1307, USA.        
 *
 * See CHANGES for updates since last release
 *
 * @author Dan Ellis, Alexandros Vellis
 * @package sieve-php
 * @copyright Copyright 2002-2003, Dan Ellis, All Rights Reserved.  
 * @version 0.1.0
 */

/**
 * Constants
 */
define ("F_NO", 0);		
define ("F_OK", 1);
define ("F_DATA", 2);
define ("F_HEAD", 3);

define ("EC_NOT_LOGGED_IN", 0);
define ("EC_QUOTA", 10);
define ("EC_NOSCRIPTS", 20);
define ("EC_UNKNOWN", 255);

/**
 * SIEVE class - A Class that implements MANAGESIEVE in PHP4|5.
 *
 * This program provides a handy interface into the Cyrus timsieved server
 * under php4.  It is tested with Sieve server included in Cyrus 2.0, but it
 * has been upgraded (not tested) to work with older Sieve server versions.
 *
 * All functions will return either true or false and will fill in
 * $sieve->error with a defined error code like EC_QUOTA, raw server errors in
 * $sieve->error_raw, and successful responses in $sieve->responses.
 *
 * NOTE: a major change since version (0.0.5) is the inclusion of a standard
 * method to retrieve  server responses.  All functions will return either true
 * or false and will fill in $sieve->error with a defined error code like
 * EC_QUOTA, raw server errors in $sieve->error_raw, and successful responses
 * in $sieve->responses.
 *
 * Usage is pretty simple.  The basics is login, do what you need and logout.
 * There are two sample files (which suck) test.php and testsieve.php.
 * test.php allows you to create/delete/view scripts and testsieve.php is a
 * very basic sieve server test.
 *
 * Please let us know of any bugs, problems or ideas at sieve-php development
 * list:  sieve-php-devel@lists.sourceforge.net. A web interface to subscribe
 * to this list is available at:
 * https://lists.sourceforge.net/mailman/listinfo/sieve-php-devel
 *
 * @author Dan Ellis
 * @example simple_example.php A simple example that shows usage of sieve-php
 * class.
 * @example vacationset-sieve.php A more elaborate example of vacation script
 * handling.
 * @version 0.1.0
 * @package sieve-php
 * @todo Maybe add the NOOP function.
 * @todo Have timing mechanism when port problems arise.
 * @todo Provide better error diagnostics. 
 */
class sieve {
  var $host;
  var $port;
  var $user;
  var $pass;
  /**
   * a comma seperated list of allowed auth types, in order of preference
   */
  var $auth_types;
  /**
   * type of authentication attempted
   */
  var $auth_in_use;
  
  /**
   * @var boolean Force disabling of STARTTLS for clients that do not want/need 
   * it. */
  var $disabletls = false;

  var $line;
  var $fp;
  var $retval;
  var $tmpfile;
  var $fh;
  var $len;
  var $script;

  var $loggedin;
  var $capabilities;
  var $error;
  var $error_raw;
  var $responses;
  
  // lastcmd is for referral processing
  var $lastcmd;
  var $reftok;
  var $refsv;
  

  //maybe we should add an errorlvl that the user will pass to new sieve = sieve(,,,,E_WARN)
  //so we can decide how to handle certain errors?!?

  /**
   * get response
   * @todo Test Cyrus version 2.2 vs version 2.1 style referrals parsing
   * @todo Perhaps do referrals like in function sieve_get_capability()
   */
  function get_response()
  {
    if($this->loggedin == false or feof($this->fp)){
        $this->error = EC_NOT_LOGGED_IN;
        $this->error_raw = "You are not logged in.";
        return false;
    }

    unset($this->response);
    unset($this->error);
    unset($this->error_raw);

    $this->line=fgets($this->fp,1024);
    $this->token = split(" ", $this->line, 2);

    if($this->token[0] == "NO"){
        /* we need to try and extract the error code from here.  There are two possibilites: one, that it will take the form of:
           NO ("yyyyy") "zzzzzzz" or, two, NO {yyyyy} "zzzzzzzzzzz" */
        $this->x = 0;
        list($this->ltoken, $this->mtoken, $this->rtoken) = split(" ", $this->line." ", 3);
        if($this->mtoken[0] == "{"){
            while($this->mtoken[$this->x] != "}" or $this->err_len < 1){
                $this->err_len = substr($this->mtoken, 1, $this->x);
                $this->x++;    
            }
            //print "<br>Trying to receive $this->err_len bytes for result<br>";
            $this->line = fgets($this->fp,$this->err_len);
            $this->error_raw[]=substr($this->line, 0, strlen($this->line) -2);    //we want to be nice and strip crlf's
            $this->err_recv = strlen($this->line);

            while($this->err_recv < $this->err_len-1){
                //print "<br>Trying to receive ".($this->err_len-$this->err_recv)." bytes for result<br>";
                $this->line = fgets($this->fp, ($this->err_len-$this->err_recv));
                $this->error_raw[]=substr($this->line, 0, strlen($this->line) -2);    //we want to be nice and strip crlf's
                $this->err_recv += strlen($this->line);
            } /* end while */
            $this->line = fgets($this->fp, 1024);	//we need to grab the last crlf, i think.  this may be a bug...
            $this->error=EC_UNKNOWN;
      
        } /* end if */
        elseif($this->mtoken[0] == "("){
            switch($this->mtoken){
                case "(\"QUOTA\")":
                    $this->error = EC_QUOTA;
                    $this->error_raw=$this->rtoken;
                    break;
                default:
                    $this->error = EC_UNKNOWN;
                    $this->error_raw=$this->rtoken;
                    break;
            } /* end switch */
        } /* end elseif */
        else{
            $this->error = EC_UNKNOWN;
            $this->error_raw = $this->line;
        }     
        return false;

    } /* end if */
    elseif(substr($this->token[0],0,2) == "OK"){
         return true;
    } /* end elseif */
    elseif($this->token[0][0] == "{"){
        
        /* Unable wild assumption:  that the only function that gets here is the get_script(), doesn't really matter though */       

        /* the first line is the len field {xx}, which we don't care about at this point */
        $this->line = fgets($this->fp,1024);
        while(substr($this->line,0,2) != "OK" and substr($this->line,0,2) != "NO"){
            $this->response[]=$this->line;
            $this->line = fgets($this->fp, 1024);
        }
        if(substr($this->line,0,2) == "OK")
            return true;
        else
            return false;
    } /* end elseif */
    elseif($this->token[0][0] == "\""){

        /* I'm going under the _assumption_ that the only function that will get here is the listscripts().
           I could very well be mistaken here, if I am, this part needs some rework */

        $this->found_script=false;        

        while(substr($this->line,0,2) != "OK" and substr($this->line,0,2) != "NO"){
            $this->found_script=true;
            list($this->ltoken, $this->rtoken) = explode(" ", $this->line." ",2);
		//hmmm, a bug in php, if there is no space on explode line, a warning is generated...
           
            if(strcmp(rtrim($this->rtoken), "ACTIVE")==0){
                $this->response["ACTIVE"] = substr(rtrim($this->ltoken),1,-1);  
            }
            else
                $this->response[] = substr(rtrim($this->ltoken),1,-1);
            $this->line = fgets($this->fp, 1024);
        } /* end while */
        
        return true;
        
    } /* end elseif */
    elseif(strstr($this->token[1], '(REFERRAL "' ) ){
    	/* process a referral, retry the lastcmd, return the results.  this is 
    	   sort of messy, really I should probably try to use parse_for_quotes
    	   but the problem is I still have the ( )'s to deal with.  This is 
    	   atleast true for timsieved as it sits in 2.1.16, if someone has a 
    	   BYE (REFERRAL ...) example for later timsieved please forward it to 
    	   me and I'll code it in proper-like! - mloftis@wgops.com */
    	$this->reftok = split(" ", $this->token[1], 3);
    	$this->refsv = substr($this->reftok[1], 0, -2);
    	$this->refsv = substr($this->refsv, 1);

        /* TODO - perform more testing */
        if(strstr($this->capabilities['implementation'], 'v2.1')) {
            /* Cyrus 2.1 - Style referrals */
        	$this->host = $this->refsv;
        } else {
            /* Cyrus 2.2 - Style referrals */
            $tmp = array_reverse( explode( '/', $this->refsv ) );
            $this->host = $tmp[0];
        }
    	$this->loggedin = false;
    	/* flush buffers or anything?  probably not, and the remote has already closed it's
    	   end by now!  */
    	fclose($this->fp);
    	
    	if( sieve::sieve_login() ) {
    		fputs($this->fp, $this->lastcmd);
    		return sieve::get_response();
    	} /* end good case happy ending */
    	else{
    		/* what to do?  login failed, should we punt and die? or log back into the referrer?
    		   i'm electing to retn EC_UNKNOWN for now and set the error string. */
    		$this->loggedin = false;
    		fclose($this->fp);
    		$this->error = EC_UNKNOWN;
    		$this->error_raw = 'UNABLE TO FOLLOW REFERRAL - ' . $this->line;
    		return false;
    	} /* end else of the unhappy ending */
    	
    	/* should never make it here! */
    	
    } /* end elseif */
    else{
            $this->error = EC_UNKNOWN;
            $this->error_raw = $this->line;
	    print '<b><i>UNKNOWN ERROR (Please report this line to <a
	    href="mailto:sieve-php-devel@lists.sourceforge.net">sieve-php-devel
	    Mailing List</a> to include in future releases):
	    '.$this->line.'</i></b><br>';

            return false;
    } /* end else */   
  } /* end get_response() */

  /**
   * Initialization of the SIEVE class.
   * 
   * It will return
   * false if it fails, true if all is well.  This also loads some arrays up
   * with some handy information:
   *
   * @param $host string hostname to connect to. Usually the IMAP server where
   * a SIEVE daemon, such as timsieved, is listening.
   *
   * @param $port string Numeric port to connect to. SIEVE daemons usually
   * listen to port 2000.
   *
   * @param $user string is the  user identity for which the SIEVE scripts
   * will be managed (also know as authcid).
   *
   * @param $pass string password to use for authentication
   *
   * @param $auth string is a super-user or proxy-user that has ACL rights to
   * login on behalf of the $auth (also know as authzid).
   *
   * @param $auth_types string a string containing all the allowed
   * authentication types allowed in order of preference, seperated by spaces.
   * (ex.  "PLAIN DIGEST-MD5 CRAM-MD5"  The method the library will try first
   * is PLAIN.) The default for this value is PLAIN.
   *
   * Note: $user, if included, is the account name (and $pass will be the
   * password) of an administrator account that can act on behalf of the user.
   * If you are using Cyrus, you must make sure that the admin account has
   * rights to admin the user.  This is to allow admins to edit/view users
   * scripts without having to know the user's password.  Very handy.
   */
  function sieve($host, $port, $user, $pass, $auth="", $auth_types='PLAIN') {
    $this->host=$host;
    $this->port=$port;
    $this->user=$user;
    $this->pass=$pass;
    if(!strcmp($auth, ""))		/* If there is no auth user, we deem the user itself to be the auth'd user */
        $this->auth = $this->user;
    else
        $this->auth = $auth;
    $this->auth_types=$auth_types;	/* Allowed authentication types */
    $this->fp=0;
    $this->line="";
    $this->retval="";
    $this->tmpfile="";
    $this->fh=0;
    $this->len=0;
    $this->capabilities="";
    $this->loggedin=false;
    $this->error= "";
    $this->error_raw="";
  }

   /**
    * Tokenize a line of input by quote marks and return them as an array
    *
    * @param $string string Input line to parse for quotes
    * @return array Array of broken by quotes parts of original string
    */
  function parse_for_quotes($string) {

      $start = -1;
      $index = 0;

      for($ptr = 0; $ptr < strlen($string); $ptr++){
          if($string[$ptr] == '"' and $string[$ptr] != '\\'){
              if($start == -1){
                  $start = $ptr;
              } /* end if */
              else{
                  $token[$index++] = substr($string, $start + 1, $ptr - $start - 1);
                  $found = true;
                  $start = -1;
              } /* end else */

          } /* end if */  

      } /* end for */

      if(isset($token))
          return $token;
      else
          return false;
  } /* end function */            

  /**
   * Parser for status responses.
   *
   * This should probably be replaced by a smarter parser.
   *
   * @param $string string Input that contains status responses.
   * @todo remove this function and dependencies
   */
  function status($string) {

      /*  Need to remove this and all dependencies from the class */

      switch (substr($string, 0,2)){
          case "NO":
              return F_NO;		//there should be some function to extract the error code from this line
					//NO ("quota") "You are oly allowed x number of scripts"
              break;
          case "OK":
              return F_OK;
              break;
          default:
              switch ($string[0]){
                  case "{":
                      //do parse here for curly braces - maybe modify
                      //parse_for_quotes to handle any parse delimiter?
                      return F_HEAD;
                      break;
                  default:
                      return F_DATA;
                      break;
              }
        }
  }
  
  /**
   * Attemp to log in to the sieve server.
   * 
   * It will return false if it fails, true if all is well.  This also loads
   * some arrays up with some handy information:
   *
   * capabilities["implementation"] contains the sieve version information
   * 
   * capabilities["auth"] contains the supported authentication modes by the
   * SIEVE server.
   * 
   * capabilities["modules"] contains the built in modules like "reject",
   * "redirect", etc.
   * 
   * capabilities["starttls"] , if is set and equal to true, will show that the
   * server supports the STARTTLS extension.
   * 
   * capabilities["unknown"] contains miscellaneous/extraneous header info sieve
   * may have sent
   *
   * @return boolean
   */
  function sieve_login() {
    $this->fp=@fsockopen($this->host,$this->port, $errno, $errstr);
    if($this->fp == false) {
        $this->error = $errno. ' '.$errstr;
        return false;
    }
 
    $this->line=fgets($this->fp,1024);

    //Hack for older versions of Sieve Server.  They do not respond with the Cyrus v2+ standard
    //response.  They repsond as follows: "Cyrus timsieved v1.0.0" "SASL={PLAIN,........}"
    //So, if we see IMPLEMENTATION in the first line, then we are done.

    if(ereg("IMPLEMENTATION",$this->line))
    {
      //we're on the Cyrus V2 or Cyrus V3 sieve server
      while(sieve::status($this->line) == F_DATA){
          $this->item = sieve::parse_for_quotes($this->line);

          if(strcmp($this->item[0], "IMPLEMENTATION") == 0)
              $this->capabilities["implementation"] = $this->item[1];
        
          elseif(strcmp($this->item[0], "SIEVE") == 0 or strcmp($this->item[0], "SASL") == 0){

              if(strcmp($this->item[0], "SIEVE") == 0) {
                  $this->cap_type="modules";
              } else {
                  $this->cap_type="auth";            
              }
              $this->modules = split(" ", $this->item[1]);
              if(is_array($this->modules)){
                  foreach($this->modules as $this->module)
                      $this->capabilities[$this->cap_type][$this->module]=true;
              } /* end if */
              elseif(is_string($this->modules))
                  $this->capabilites[$this->cap_type][$this->modules]=true;
          }    
          elseif(strcmp($this->item[0], "STARTTLS") == 0) {
	          $this->capabilities['starttls'] = true;
	  
          }
	  else{ 
              $this->capabilities["unknown"][]=$this->line;
          }    
      $this->line=fgets($this->fp,1024);

       }// end while
    }
    else
    {
        //we're on the older Cyrus V1. server  
        //this version does not support module reporting.  We only have auth types.
        $this->cap_type="auth";
       
        //break apart at the "Cyrus timsieve...." "SASL={......}"
        $this->item = sieve::parse_for_quotes($this->line);

        $this->capabilities["implementation"] = $this->item[0];

        //we should have "SASL={..........}" now.  Break out the {xx,yyy,zzzz}
        $this->modules = substr($this->item[1], strpos($this->item[1], "{"),strlen($this->item[1])-1);

        //then split again at the ", " stuff.
        $this->modules = split($this->modules, ", ");
 
        //fill up our $this->modules property
        if(is_array($this->modules)){
            foreach($this->modules as $this->module)
                $this->capabilities[$this->cap_type][$this->module]=true;
        } /* end if */
        elseif(is_string($this->modules))
            $this->capabilites[$this->cap_type][$this->module]=true;
    }

    if(sieve::status($this->line) == F_NO){		//here we should do some returning of error codes?
        $this->error=EC_UNKNOWN;
        $this->error_raw = "Server not allowing connections.";
        return false;
    }

    /* decision login to decide what type of authentication to use... */

    /* If we allow STARTTLS, use it */ 
    if($this->capabilities['starttls'] === true && function_exists('stream_socket_enable_crypto') === true
       && !$this->disabletls ) {
        fputs($this->fp,"STARTTLS\r\n");
        $starttls_response = $this->line=fgets($this->fp,1024);
        if(stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) == false) {
            $this->error=EC_UNKNOWN;
            $this->error_raw = "Failed to establish TLS connection.";
            return false;
        } else {
            $this->loggedin = true;
            // RFC says that we need to ask for the capabilities again
            $this->sieve_get_capability();
            $this->loggedin = false;
        }
    }

    /* Loop through each allowed authentication type and see if the server allows the type */
    foreach(explode(" ", $this->auth_types) as $auth_type) {
        if ($this->capabilities["auth"][$auth_type]) {
            /* We found an auth type that is allowed. */
            $this->auth_in_use = $auth_type;
        }
    }
    
    /* call our authentication program */
    return sieve::authenticate();
  }

  /**
   * Log out of the sieve server.
   *
   * @return boolean Always returns true at this point.
   */
  function sieve_logout() {
    if($this->loggedin==false)
        return false;

    fputs($this->fp,"LOGOUT\r\n");
    fclose($this->fp);
    $this->loggedin=false;
    return true;
  }

  /**
   * Send the script contained in $script to the server.
   *
   * It will return any error results it finds (in $sieve->error and
   * $sieve->error_raw), and return true if it is successfully sent.  The
   * function does _not_ automatically make the script the active script.
   *
   * @param $scriptname string The name of the SIEVE script.
   * @param $script The script to be uploaded.
   * @return boolean Returns true if script has been successfully uploaded.
   */
  function sieve_sendscript($scriptname, $script) {
    if($this->loggedin==false)
        return false;
    $this->script=stripslashes($script);
    $len=strlen($this->script);
    
    $this->lastcmd = 'PUTSCRIPT "'.$scriptname.'" {'.$len.'+}'."\r\n".$this->script."\r\n";
    fputs($this->fp, $this->lastcmd);
    return sieve::get_response();

  }  
  
  /**
   * Check if there is enough space for a script to be uploaded. 
   *
   * This function returns true or false based on whether the sieve server will
   * allow your script to be sent and your quota has not been exceeded.  This
   * function does not currently work due to a believed bug in timsieved.  It
   * could be my code too.
   *
   * It appears the timsieved does not honor the NUMBER type.  see lex.c in
   * timsieved src.  don't expect this function to work yet.  I might have
   * messed something up here, too.
   *
   * @param $scriptname string The name of the SIEVE script.
   * @param $scriptsize integer The size of the SIEVE script.
   * @return boolean
   * @todo Does not work; bug fix and test.
   */
  function sieve_havespace($scriptname, $scriptsize)   {
    if($this->loggedin==false)
        return false;
        
    $this->lastcmd = "HAVESPACE \"$scriptname\" $scriptsize\r\n";
    fputs($this->fp, $this->lastcmd);
    return sieve::get_response();
  }  

  /**
   * Set the script active on the sieve server.
   *
   * @param $scriptname string The name of the SIEVE script.
   * @return boolean
   */
  function sieve_setactivescript($scriptname)   {
    if($this->loggedin==false)
        return false;
    
		$this->lastcmd = "SETACTIVE \"$scriptname\"\r\n";
    fputs($this->fp, $this->lastcmd);   
    return sieve::get_response();

  }
  
  /**
   * Return the contents of the requested script.
   * 
   * If you want to display the script, you will need to change all CrLf to
   * '.'.
   *
   * @param $scriptname string The name of the SIEVE script.
   * @return arr SIEVE script data.
   */
  function sieve_getscript($scriptname) {
    unset($this->script);
    if($this->loggedin==false)
        return false;
        
    $this->lastcmd = "GETSCRIPT \"$scriptname\"\r\n";
    fputs($this->fp, $this->lastcmd);
    return sieve::get_response();
  }

  /**
   * Attempt to delete the script requested.
   *
   * If the script is currently active, the server will not have any active
   * script after the deletion.
   *
   * @param $scriptname string The name of the SIEVE script.
   * @return mixed
   */
  function sieve_deletescript($scriptname)   {
    if($this->loggedin==false)
        return false;
        
		$this->lastcmd = "DELETESCRIPT \"$scriptname\"\r\n";
    fputs($this->fp, $this->lastcmd);    

    return sieve::get_response();
  }

  
  /**
   * List available scripts on the SIEVE server.
   *
   * This function returns true or false.  $sieve->response will be filled
   * with the names of the scripts found.  If a script is active, the
   * $sieve->response["ACTIVE"] will contain the name of the active script.
   *
   * @return boolean
   */
  function sieve_listscripts() { 
  	 $this->lastcmd = "LISTSCRIPTS\r\n";
     fputs($this->fp, $this->lastcmd); 
     sieve::get_response();		//should always return true, even if there are no scripts...
     if(isset($this->found_script) and $this->found_script)
         return true;
     else{
         $this->error=EC_NOSCRIPTS;	//sieve::getresponse has no way of telling wether a script was found...
         $this->error_raw="No scripts found for this account.";
         return false;
     }
   }


  /**
   * Check availability of connection to the SIEVE server.
   *
   * This function returns true or false based on whether the connection to the
   * sieve server is still alive.
   *
   * @return boolean
   */
  function sieve_alive()   {
      if(!isset($this->fp) or $this->fp==0){
          $this->error = EC_NOT_LOGGED_IN;
          return false;
      }
      elseif(feof($this->fp)){			
          $this->error = EC_NOT_LOGGED_IN;
          return false;
      }
      else
          return true;
  }

  /**
   * Perform SASL authentication to SIEVE server.
   *
   * Attempts to authenticate to SIEVE, using some SASL authentication method
   * such as PLAIN or DIGEST-MD5.
   *
   */
  function authenticate() {

    switch ($this->auth_in_use) {

        case "PLAIN":
            $auth=base64_encode($this->user."\0".$this->auth."\0".$this->pass);
   
            $this->len=strlen($auth);			
            fputs($this->fp, 'AUTHENTICATE "PLAIN" {' . $this->len . '+}' . "\r\n");
            fputs($this->fp, "$auth\r\n");

            $this->line=fgets($this->fp,1024);		
            while(sieve::status($this->line) == F_DATA)
               $this->line=fgets($this->fp,1024);

             if(sieve::status($this->line) == F_NO)
               return false;
             $this->loggedin=true;
               return true;    
	    break;
	
        case "DIGEST-MD5":
	     // SASL DIGEST-MD5 support works with timsieved 1.1.0
	     // follows rfc2831 for generating the $response to $challenge
	     fputs($this->fp, "AUTHENTICATE \"DIGEST-MD5\"\r\n");
	     // $clen is length of server challenge, we ignore it. 
	     $clen = fgets($this->fp, 1024);
	     // read for 2048, rfc2831 max length allowed
	     $challenge = fgets($this->fp, 2048);
	     // vars used when building $response_value and $response
	     $cnonce = base64_encode(bin2hex(hmac_md5(microtime())));
	     $ncount = "00000001";
	     $qop_value = "auth"; 
	     $digest_uri_value = "sieve/$this->host";
	     // decode the challenge string
	     $result = decode_challenge($challenge);
	     // verify server supports qop=auth 
	     $qop = explode(",",$result['qop']);
	     if (!in_array($qop_value, $qop)) {
	        // rfc2831: client MUST fail if no qop methods supported
	        return false;
	     }
	     // build the $response_value
	     $string_a1 = utf8_encode($this->user).":";
	     $string_a1 .= utf8_encode($result['realm']).":";
	     $string_a1 .= utf8_encode($this->pass);
	     $string_a1 = hmac_md5($string_a1);
	     $A1 = $string_a1.":".$result['nonce'].":".$cnonce.":".utf8_encode($this->auth);
	     $A1 = bin2hex(hmac_md5($A1));
	     $A2 = bin2hex(hmac_md5("AUTHENTICATE:$digest_uri_value"));
	     $string_response = $result['nonce'].":".$ncount.":".$cnonce.":".$qop_value;
	     $response_value = bin2hex(hmac_md5($A1.":".$string_response.":".$A2));
	     // build the challenge $response
	     $reply = "charset=utf-8,username=\"".$this->user."\",realm=\"".$result['realm']."\",";
	     $reply .= "nonce=\"".$result['nonce']."\",nc=$ncount,cnonce=\"$cnonce\",";
	     $reply .= "digest-uri=\"$digest_uri_value\",response=$response_value,";
	     $reply .= "qop=$qop_value,authzid=\"".utf8_encode($this->auth)."\"";
	     $response = base64_encode($reply);
	     fputs($this->fp, "\"$response\"\r\n");
 	
             $this->line = fgets($this->fp, 1024);
             while(sieve::status($this->line) == F_DATA)
                $this->line = fgets($this->fp,1024);

             if(sieve::status($this->line) == F_NO)
               return false;
             $this->loggedin = TRUE;
               return TRUE;    
             break;
	
        case "CRAM-MD5":
  	     // SASL CRAM-MD5 support works with timsieved 1.1.0
	     // follows rfc2195 for generating the $response to $challenge
	     // CRAM-MD5 does not support proxy of $auth by $user
	     // requires php mhash extension
	     fputs($this->fp, "AUTHENTICATE \"CRAM-MD5\"\r\n");
	     // $clen is the length of the challenge line the server gives us
	     $clen = fgets($this->fp, 1024);
	     // read for 1024, should be long enough?
	     $challenge = fgets($this->fp, 1024);
	     // build a response to the challenge
	     $hash = bin2hex(hmac_md5(base64_decode($challenge), $this->pass));
	     $response = base64_encode($this->user." ".$hash);
	     // respond to the challenge string
	     fputs($this->fp, "\"$response\"\r\n");
	     
             $this->line = fgets($this->fp, 1024);		
             while(sieve::status($this->line) == F_DATA)
                $this->line = fgets($this->fp,1024);

             if(sieve::status($this->line) == F_NO)
               return false;
             $this->loggedin = TRUE;
               return TRUE;    
             break;

	case "LOGIN":
 	     $login=base64_encode($this->user);
 	     $pass=base64_encode($this->pass);
 	
 	     fputs($this->fp, "AUTHENTICATE \"LOGIN\"\r\n");
 	     fputs($this->fp, "{".strlen($login)."+}\r\n");
 	     fputs($this->fp, "$login\r\n");
 	     fputs($this->fp, "{".strlen($pass)."+}\r\n");
 	     fputs($this->fp, "$pass\r\n");
 
	     $this->line=fgets($this->fp,1024);
 	     while(sieve::status($this->line) == F_HEAD ||
 	           sieve::status($this->line) == F_DATA)
 	         $this->line=fgets($this->fp,1024);
 	
 	     if(sieve::status($this->line) == F_NO)
 	         return false;
 	     $this->loggedin=true;
 	     return true;
 	     break;

        default:
            return false;
            break;

    }//end switch
  }

  /**
   * Return an array of available capabilities.
   *
   * @return array
   */
  function sieve_get_capability() {
    if($this->loggedin==false)
        return false;
    fputs($this->fp, "CAPABILITY\r\n"); 
    $this->line=fgets($this->fp,1024);

    $tmp = array();
    if(preg_match('|^BYE \(REFERRAL "(sieve://)?([^/"]+)"\)|', $this->line, $tmp ) ){
        $this->host = $tmp[2];
        $this->loggedin = false;
        fclose($this->fp);

        if( sieve::sieve_login() ) {
            return $this->sieve_get_capability();
        } else {
            $this->loggedin = false;
            fclose($this->fp);
            $this->error = EC_UNKNOWN;
            $this->error_raw = 'UNABLE TO FOLLOW REFERRAL - ' . $this->line;
            return false;
        }
    }

    while(sieve::status($this->line) == F_DATA){
       $this->item = sieve::parse_for_quotes($this->line);

       if(strcmp($this->item[0], "IMPLEMENTATION") == 0) {
           $this->capabilities["implementation"] = $this->item[1];

       } elseif(strcmp($this->item[0], "SIEVE") == 0 or strcmp($this->item[0], "SASL") == 0){

              $cap_type = '';
              if(strcmp($this->item[0], "SIEVE") == 0) {
                  $cap_type="modules";
              } else {
                  $cap_type="auth";            
              }

              $this->modules = split(' ', $this->item[1]);
              if(is_array($this->modules)){
                  foreach($this->modules as $m) {
                      $this->capabilities[$cap_type][$m]=true;
                  }
              } elseif(is_string($this->modules)) {
                  $this->capabilites[$cap_type][$this->modules]=true;
              }
          } else { 
              $this->capabilities["unknown"][]=$this->line;
          }    
      $this->line=fgets($this->fp,1024);

    }// end while
    return $this->capabilities['modules'];
  }

}


/**
 * The following functions are support functions and might be handy to the
 * sieve class.
 */

if(!function_exists('hmac_md5')) {

/**
 * Creates a HMAC digest that can be used for auth purposes.
 * See RFCs 2104, 2617, 2831
 * Uses mhash() extension if available
 *
 * Squirrelmail has this function in functions/auth.php, and it might have been
 * included already. However, it helps remove the dependancy on mhash.so PHP
 * extension, for some sites. If mhash.so _is_ available, it is used for its
 * speed.
 *
 * This function is Copyright (c) 1999-2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * @param string $data Data to apply hash function to.
 * @param string $key Optional key, which, if supplied, will be used to
 * calculate data's HMAC.
 * @return string HMAC Digest string
 */
function hmac_md5($data, $key='') {
    // See RFCs 2104, 2617, 2831
    // Uses mhash() extension if available
    if (extension_loaded('mhash')) {
      if ($key== '') {
        $mhash=mhash(MHASH_MD5,$data);
      } else {
        $mhash=mhash(MHASH_MD5,$data,$key);
      }
      return $mhash;
    }
    if (!$key) {
         return pack('H*',md5($data));
    }
    $key = str_pad($key,64,chr(0x00));
    if (strlen($key) > 64) {
        $key = pack("H*",md5($key));
    }
    $k_ipad =  $key ^ str_repeat(chr(0x36), 64) ;
    $k_opad =  $key ^ str_repeat(chr(0x5c), 64) ;
    /* Heh, let's get recursive. */
    $hmac=hmac_md5($k_opad . pack("H*",md5($k_ipad . $data)) );
    return $hmac;
}
}

/**
 * A hack to decode the challenge from timsieved 1.1.0.
 * 
 * This function may not work with other versions and most certainly won't work
 * with other DIGEST-MD5 implentations
 *
 * @param $input string Challenge supplied by timsieved.
 */
function decode_challenge ($input) {
    $input = base64_decode($input);
    preg_match("/nonce=\"(.*)\"/U",$input, $matches);
    $resp['nonce'] = $matches[1];
    preg_match("/realm=\"(.*)\"/U",$input, $matches);
    $resp['realm'] = $matches[1];
    preg_match("/qop=\"(.*)\"/U",$input, $matches);
    $resp['qop'] = $matches[1];
    return $resp;
}

// vim:ts=4:et:ft=php

?>
