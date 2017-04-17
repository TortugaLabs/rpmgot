#!/usr/bin/php-cgi
<?php
#
#   rpmgot
#   Copyright (C) 2010 Alejandro Liu Ly
#
#   This file is part of rpmgot
#
#   rpmgot is free software; you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as
#   published by the Free Software Foundation; either version 2 of 
#   the License, or (at your option) any later version.
#
#   rpmgot is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public
#   License along with this program.  If not, see 
#   <http://www.gnu.org/licenses/>
#

//////////////////////////////////////////////////////////////////////
//
// Tweakable settings
//
//////////////////////////////////////////////////////////////////////
// max time for socket reads
if(!@defined('FF_RECV_TIME')) define('FF_RECV_TIME', 30);
// max time for socket connect
if(!@defined('FF_CONN_TIME')) define('FF_CONN_TIME', 5);
// Buffer size... increaing this should improve streaming performance
// but chokes the lighttpd server...
if (!@defined('FF_BUFSZ')) define('FF_BUFSZ',1024*4);
//
// Global configuraiton settings
//
$cf['baseurl'] = "http://".$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"];
// If offline it will not try to retrieve files from upstream servers
$cf['offline'] = false;
//
// How long we want to cache index files.  Static files are cached 
// permanently
$cf['expire_hours'] = 8;
//
// Location of the cache directory
$cf["cache_dir"] = "rpmgot-cache/";
//
// URL for the cache directory in redirections.  When the file is fully
// download we simply do a HTTP redirect and let the web server handle it.
$cf["cache_url"] = dirname($_SERVER['SCRIPT_NAME']).'/'.$cf['cache_dir'];

// URL to start a fetcher task to download from the upstream server
// Needed because we can't rely on "fork" being available.
$cf["fetcher_url"] = $cf['baseurl'];
// We need to do it this way because from the DMZ the hostname
// is SERVER_NAME value is not valid...
$cf['fetcher_url'] = 'http://localhost/'.$_SERVER['SCRIPT_NAME'];

//////////////////////////////////////////////////////////////////////
//
// VHOST configuration
//
//////////////////////////////////////////////////////////////////////
//
// CentOS repos
//
$cf["vhosts"]["EPEL"] = 
  array(
	"name" => 'EPEL for $releasever - $basearch',
	"gpgkey" => 'http://download.fedora.redhat.com/pub/epel/RPM-GPG-KEY-EPEL-$releasever',
	"baseurl" => '/$releasever/$basearch',
	"select" => array("c",'c6'),
	"mirrors" => array(
			   // "http://mirrors.nl.eu.kernel.org/fedora-epel/",
			   // "http://ftp.nluug.nl/pub/os/Linux/distr/fedora-epel/",
			   "http://nl.mirror.eurid.eu/epel/",
			   "http://mirror.pnl.gov/epel/",
			   'http://mirrors.servercentral.net/fedora/epel/',
			   )
	);
$cf['vhosts']['EPEL-buildsys'] = 
  array(
	'name' => 'EPEL build RPMs',
	'baseurl' => '/rhel$releasever/$basearch',
	'select' => array('c'),
	'mirrors' => array(
			   'http://buildsys.fedoraproject.org/buildgroups/',
			   )
	);


$cf["vhosts"]["centos"] =
  array(
	"name" => 'CentOS $releasever $basearch - $repos',
	"gpgkey" => 'file:///etc/pki/rpm-gpg/RPM-GPG-KEY-CentOS-$releasever',
	"baseurl" => '/$releasever/$repos/$basearch',
	"select" => array("c",'c6'),
	"vars" => array(
			"repos" => array('extras','updates')
			),
	'mirrors' => array(
			   "http://mirror.widexs.nl/ftp/pub/os/Linux/distr/centos/",
			   // "http://ftp.nluug.nl/ftp/pub/os/Linux/distr/CentOS/",
			   // "http://mirrors.nl.kernel.org/centos/",
			   "http://ftp.tudelft.nl/centos.org/",
			   'http://mirror.nl.leaseweb.net/centos/',
			   'http://mirror.yourwebhoster.eu/centos/',
			   'http://centos.mirror.triple-it.nl/',
			   'http://mirror.oxilion.nl/centos/',
			   )
	);

$cf["vhosts"]["elrepo"] = 
  array(
	'name' => 'EL Repo.org $releasever - $basearch',
	'gpgkey' => 'http://elrepo.org/RPM-GPG-KEY-elrepo.org',
	"baseurl" => '/el$releasever/$basearch',
	"select" => array("c",'c6'),
	'mirrors' => array(
			   "http://elrepo.org/linux/elrepo/"
			   )
	);

# We are only adding testing... update is quite empty
$cf["vhosts"]["rpmfusion-el"] =
  array(
	'name' => 'rpmfusion $repo testing $releasever - $basearch',
	'gpgkey' => 'file:///usr/local/etc/pki-centos/RPM-GPG-KEY-rpmfusion-$repo-el',
	"baseurl" => '/$repo/el/updates/testing/$releasever/$basearch/',
	"vars" => array(
			"repo" => array('free','nonfree'),
			),
	"select" => array("c"),
	'mirrors' => array(
			   "http://download1.rpmfusion.org/",
			   "http://mirror.switch.ch/ftp/mirror/rpmfusion/",
			   "http://ftp-stud.hs-esslingen.de/pub/Mirrors/rpmfusion.org/",
			   "http://fedora.tu-chemnitz.de/pub/linux/rpmfusion/",
			   "http://fr2.rpmfind.net/linux/rpmfusion/",
			   "http://mirror01.th.ifl.net/rpmfusion/",
			   )
	);

$cf["vhosts"]["gitco-xen4"] = 
  array(
	'name' => 'Xen $releasever - $basearch',
	'gpgkey' => 'http://www.gitco.de/linux/$basearch/centos/$releasever/GITCO-RPM-KEY',
	"baseurl" => '/$basearch/centos/$releasever/xen4.0.1/',
	"select" => array('c6'),
	'mirrors' => array(
			   'http://www.gitco.de/linux/'
			   )
	);
$cf["vhosts"]["gitco-dom0"] = 
  array(
	'name' => 'dom0 $releasever - $basearch',
	'gpgkey' => 'http://www.gitco.de/linux/$basearch/centos/$releasever/GITCO-RPM-KEY',
	"baseurl" => '/$basearch/centos/$releasever/kernel-dom0/',
	"select" => array('n'),
	'mirrors' => array(
			   'http://www.gitco.de/linux/'
			   )
	);
$cf["vhosts"]["gitco-misc"] = 
  array(
	'name' => 'gitco-misc $releasever - $basearch',
	'gpgkey' => 'http://www.gitco.de/linux/$basearch/centos/$releasever/GITCO-RPM-KEY',
	"baseurl" => '/$basearch/centos/$releasever/misc/',
	"select" => array('c6'),
	'mirrors' => array(
			   'http://www.gitco.de/linux/'
			   )
	);


//
// Fedora repos
//

//
// Main Fedora repositories
//
$cf["vhosts"]["Fedora-rel"] =
  array(
	'name' => 'Fedora Release $releasever - $basearch',
	'gpgkey' => 'file:///etc/pki/rpm-gpg/RPM-GPG-KEY-fedora-$basearch',
	"select" => array("f"),
	"baseurl" => '/$releasever/Everything/$basearch/os',
	'mirrors' => array(
			   "http://ftp.nluug.nl/pub/os/Linux/distr/fedora/linux/releases/",
			   "http://mirrors.nl.eu.kernel.org/fedora/releases/",
			   // "http://mirror.leaseweb.com/fedora/linux/releases/",
			   "http://ftp.tudelft.nl/download.fedora.redhat.com/linux/releases/",
			   "http://nl.mirror.eurid.eu/fedora/linux/releases/",
			   )
	);
$cf["vhosts"]["Fedora-upd"] = 
  array(
	'name' => 'Fedora Updates $releasever - $basearch',
	'gpgkey' => 'file:///etc/pki/rpm-gpg/RPM-GPG-KEY-fedora-$basearch',
	"select" => array("f"),
	"baseurl" => '/$releasever/$basearch',
	'mirrors' => array(
			   "http://mirrors.nl.eu.kernel.org/fedora/updates/",
			   //"http://mirror.leaseweb.com/fedora/linux/updates/",
			   "http://ftp.tudelft.nl/download.fedora.redhat.com/linux/updates/",
			   // "http://ftp.nluug.nl/pub/os/Linux/distr/fedora/linux/updates/",
			   "http://nl.mirror.eurid.eu/fedora/linux/updates/",
			   )
	);
//
// RPM Fusion
//
$cf["vhosts"]["rpmfusion-os"] =
  array(
	'name' => 'rpmfusion-$repo-$channel $releasever - $basearch',
	'gpgkey' => 'file:///usr/local/etc/pki-fedora/RPM-GPG-KEY-rpmfusion-$repo-fedora-$releasever-$basearch',
	"select" => array("f"),
	"vars" => array(
			"repo" => array('free','nonfree'),
			"channel" => 
			array(
			      'releases/$releasever/Everything/$basearch/os',
			      'updates/$releasever/$basearch'
			      ),
			),
	"baseurl" => '/$repo/fedora/$channel',
	'mirrors' => array(
			   'http://download1.rpmfusion.org/',
			   "http://mirror.switch.ch/ftp/mirror/rpmfusion/",
			   "http://ftp-stud.hs-esslingen.de/pub/Mirrors/rpmfusion.org/",
			   "http://fedora.tu-chemnitz.de/pub/linux/rpmfusion/",
			   "http://fr2.rpmfind.net/linux/rpmfusion/",
			   "http://mirror01.th.ifl.net/rpmfusion/"
			   )
	);

/*
  Additional repos that we could configure...


  // Currently seems dead...
  // Livna
	  'gpgkey' => 'http://rpm.livna.org/RPM-LIVNA-GPG-KEY',
	  'mirrors' => array(
			     "http://rpm.livna.org/repo/$releasever/$basearch/",
			     "http://wftp.tu-chemnitz.de/pub/linux/livna/repo/$releasever/$basearch/",
			     "http://ftp-stud.fht-esslingen.de/pub/Mirrors/rpm.livna.org/repo/$releasever/$basearch/",
			     )

// Skype... would you believe that they do this?
	'mirrors' => array(
			   "http://download.skype.com/linux/repos/fedora/updates/i586"

[Dropbox]
name=Dropbox Repository
baseurl=http://linux.dropbox.com/fedora/$releasever/
gpgkey=http://linux.dropbox.com/fedora/rpm-public-key.asc



*/

//
// These ones should not need any changes
// 
// We only allow these type of files to be proxied.
// static files are cached permanently, while index_files
// are refreshed on a regular basis.
$cf["static_files"] = array(
			    # Red-hat static files
			    "\.rpm",
			    "\.img",
			    "RPM-GPG-KEY.*",
			    "vmlinuz",
			    ".treeinfo"
			    );
$cf["index_files"] = array(
			# Red-hat index files
			   "comps.*\.xml",
			   ".+-comps\.xml",
			   ".+-comps\.xml\.gz",
			   "filelists\.sqlite\.bz2",
			   "filelists\.xml\.gz",
			   "other\.sqlite\.bz2",
			   "other\.xml\.gz",
			   "primary\.sqlite\.bz2",
			   "primary\.xml\.gz",
			   "repomd\.xml",
			   "updateinfo\.xml\.gz",
			   "pkgtags\.sqlite\.gz",
			   "yumgroups\.xml"
			   );
//////////////////////////////////////////////////////////////////////
//
// End of user configuration
//
//////////////////////////////////////////////////////////////////////
define('FF_CRLF',chr(13).chr(10));


function log_msg($file,$msg) {
  global $cf;
  $fp= fopen($cf["cache_dir"].$file,"a");
  if (!$fp) return;
  if (flock($fp,LOCK_EX)) {
    fwrite($fp,date("Y-m-d H:i:s")." ".$_SERVER["REMOTE_ADDR"]." ".
	   ( isset($_SERVER["PATH_INFO"]) ? $_SERVER["PATH_INFO"] : "-" ).
	   " ".$msg."\n");
    flock($fp,LOCK_UN);
  }
  fclose($fp);
}
function debug_msg($msg) {
  log_msg("debug.log",$msg);
}

# Snippet from PHP Share: http://www.phpshare.org
// Displays the file size given the number of bytes
function fmtSzUnits($bytes) {
  if ($bytes >= 1073741824)
    {
      $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    }
  elseif ($bytes >= 1048576)
    {
      $bytes = number_format($bytes / 1048576, 2) . ' MB';
    }
  elseif ($bytes >= 1024)
    {
      $bytes = number_format($bytes / 1024, 2) . ' KB';
    }
  elseif ($bytes > 1)
    {
      $bytes = $bytes . ' bytes';
    }
  elseif ($bytes == 1)
    {
      $bytes = $bytes . ' byte';
    }
  else
    {
      $bytes = '0 bytes';
    }
  
  return $bytes;
}

/*  returns a socket pointer if valid or displays an error message
    sets stream timeout, starts the clock to check for socket read time */
function ff_get_sock($target,$port,&$errno,&$errstr){
  if(false===($fp = @fsockopen($target,$port,$errno,$errstr,FF_CONN_TIME))||!is_resource($fp))
    return false;
  @stream_set_timeout($fp, FF_RECV_TIME);
  return $fp;
}

/*  handles fsockopen errors, printing them out though you may want to die on err */
function ff_sock_strerror($errno){
  switch($errno){
    case -3:  $err="Socket creation failed"; break;
    case -4:  $err="DNS lookup failure"; break;
    case -5:  $err="Connection refused or timed out"; break;
    case 5:   $err="I/O error"; break;
    case 22:  $err="Invalid argument"; break;
    case 72:  $err="Protocol error"; break;
    case 111: $err="Connection refused"; break;
    case 113: $err="No route to host"; break;
    case 110: $err="Connection timed out"; break;
    case 104: $err="Connection reset by client"; break;
    default:  $err="Connection failed"; break;
  }
  return $err;
}

/* 
 * fetch_dl 

 * initiates the socket and download for the passed url.
 * uses the long2ip/ip2long for ip validation, uses gethostbyname to
 * get the ipv4 address which saves fsockopen from having to do the lookup
 * final data is saved to $rbody but currently only displays headers.
 */
function fetch_dl($url,&$errno,&$errstr,$fd = NULL){
  do {
    $ub = @parse_url($url);
    if(!isset($ub['host'])||empty($ub['host'])) {
      $errno =  22;
      $errstr = "bad url $url";
      return false;
    }
    $proto   = ($ub['scheme']=='https')?'ssl://':'';
    $port   = (isset($ub['port'])&&!empty($ub['port'])) ? $ub['port']:($proto!='')?443:80;
    $path   = (isset($ub['path'])&&!empty($ub['path'])) ? $ub['path']:'/';
    $query   = (isset($ub['query'])&&!empty($ub['query'])) ? '?'.$ub['query'] : '';
    $host   = $ub['host'];
    $ipp     = @gethostbyname($host);
    $ip     = ($ipp!=$host) ? long2ip(ip2long($ipp)) : $host;
    $method = $fd == NULL ? "HEAD" : "GET";

    $headers=array(
		   "{$method} {$path}{$query} HTTP/1.1",
		   "Host: {$host}",
		   'User-Agent: RPMGOT Cacher',
		   'Accept: */*',
		   'Accept-Language: en-us,en;q=0.5',
		   'Accept-Charset: utf-8;q=0.7,*;q=0.7',
		   'Connection: close','Referer: http://www.0ink.net/'
		   );
    $request=join(FF_CRLF,$headers).FF_CRLF.FF_CRLF;
		
    $fp=ff_get_sock($proto.$ip, $port,$errno,$errstr);
    if ($fp === false) {
      return false;
    }

    // echo("REQUEST TO SEND:<pre>$request<pre>");
    // Send our request....
    if (!@fwrite($fp,$request,strlen($request))) {
      $errno = 5;
      $errstr = "unable to send request";
      return false;
    }

    // We want to get the header first...
    $rbuf = '';
    $rdat = '';

    while (strstr(substr($rdat,0,
			 strlen($rdat) > strlen($rbuf) + 4 ?
			 0 : strlen($rdat) - strlen($rbuf) - 4),
		  FF_CRLF.FF_CRLF) === FALSE) {
      if (feof($fp)) {
	if ($fd == NULL) break;
	// EOF before full headers...
	$errno = 5;
	$errstr = "unexpected EOF while reading headers";
	return false;
      }
      $rbuf = fread($fp, FF_BUFSZ);
      $rdat .= $rbuf;
    }


    // OK, we have full headers...
    list($rbuf,$rdat) = explode(FF_CRLF.FF_CRLF,$rdat,2);
    
    $hdr = array();
    foreach (explode(FF_CRLF,$rbuf) as $h) {
      if (!count($hdr)) {
	// First line... HTTP response line
	list($proto,$code,$msg) = preg_split('/\s/',$h,3);
	$hdr[":proto"] = $proto;
	$hdr[":status"] = $code;
	$hdr[":status-msg"] = $msg;
      } else {
	list($l,$r) = explode(':',$h,2);
	$hdr[trim($l)] = trim($r);
      }
    }
    if ($fd == NULL) {
      return $hdr;
    }
    // Check if it is a redirect...
    if (substr($hdr[":status"],0,1) == "3") {
      if (!isset($hdr["Location"])) {
	$errno = 72;
	$errstr = "Missing location";
	return false;
      }
      $url = $hdr["Location"];
    } else {
      $url = "";
    }
  } while ($url != "");

  if (substr($hdr[":status"],0,1) != "2") {
    $errno = 72;
    $errstr = "Remote status: ".$hdr[":status"];
    return false;
  }

  fwrite($fd,$rdat); // Write out the current data...
  while (!feof($fp)) {
    set_time_limit(30);	// Reset the executiom timer...
    $rbuf = fread($fp,FF_BUFSZ);
    fwrite($fd,$rbuf);
    flush();
  }
  return $hdr;
}

/*
 * Send error messages
 */
function sendresp($code,$msg="") {
  debug_msg("SENDRESP: " .$code.",".$msg);
  header($_SERVER["SERVER_PROTOCOL"].' '.$code.' '.$msg);
  ?>
    <html>
       <head><title>Error: <?=$code?></title></head>
       <body>
         <h1>Error: <?=$code?></h1>
         <hr/>
         <?=$msg?>
         <hr/>
         <pre>
         _SERVER:
         <? print_r($_SERVER); ?>
         </pre>
       </body>
    </html>
    <?php
  exit();
}

/*
 * Match patterns
 */
function match_patterns($s,$mm) {
  global $pat,$cf;
  if (!isset($pat[$mm])) {
    $pat[$mm] = '/('.implode("|",$cf[$mm]).')/';
  }
  return preg_match($pat[$mm],$s);
}
function is_static_file($s) {
  return match_patterns($s,"static_files");
}
function is_index_file($s) {
  return match_patterns($s,"index_files");
}

// Global locks
function grab_lock($lock,$msg="") {
  global $lock_fh;
  global $cf;

  if (!is_dir($cf["cache_dir"].$lock)) {
    if (!mkdir($cf["cache_dir"].$lock,0777,true)) {
      sendresp(403,"System error2: mkdir($dn)");
    }
  }
  $lock_fh = fopen($cf["cache_dir"].$lock."/lock","c+");
  if ($lock_fh === false || !flock($lock_fh,LOCK_EX)) {
    debug_msg("grab_lock: Failed");
    die("Unable to lock $lock");
  }
}
function release_lock($lock) {
  global $lock_fh;
  flock($lock_fh,LOCK_UN);
  fclose($lock_fh);
}

function pick_rnd_vhost($vhost) {
  global $cf;
  // In theory we could also get a mirrorlist URL here...
  $mirrors =$cf["vhosts"][$vhost]["mirrors"];

  if (count($mirrors) > 1) {
    $i = rand(0,count($mirrors)-1);
  } else {
    $i = 0;
  }
  return $mirrors[$i];
}

// Retrieve header (trying all mirrors)
function fetch_hdr($vhost,$uri,&$xhost, &$errno,$errstr) {
  global $cf;
  // In theory we could also get a mirrorlist URL here...
  $mirrors =$cf["vhosts"][$vhost]["mirrors"];

  $k = count($mirrors);

  if ($k > 1) {
    $i = rand(0,$k-1);
    $i = 0;
  } else {
    $i = 0;
  }
  for ($j= 0; $j <= $k; $j++) {
    $xhost = $mirrors[($i+$j) % $k];
    debug_msg("FECH: from $xhost");
    $hdr = fetch_dl($xhost.$uri,$errno,$errstr);
    if ($hdr && $hdr[":status"] != 404) break;
    debug_msg("Not found on $xhost$uri ... switching mirror ($j/$k)");
  }
  // None worked...
  return $hdr;
}

/*
 * Parse range requests
 */

function parse_range($req,$len) {
  if ($req == '' || $len == '') {
    return '';
  }
  if (!preg_match('/^bytes=/',$req)) {
    return '';
  }
  $req = preg_replace('/^bytes=/','',$req);
  $res = array();

  foreach (explode(',',$req) as $rr) {
    if (preg_match('/^(\d+)$/',$rr,$mv)) {
      // Single byte
      array_push($res,array($mv[1],$mv[1]));
    } elseif (preg_match('/^(\d+)-$/',$rr,$mv)) {
      // Till end of file
      array_push($res,array($mv[1],''));
    } elseif (preg_match('/^(\d+)-(\d+)$/',$rr,$mv)) {
      // Fully bounded range
      array_push($res,array($mv[1],$mv[2]));
    } elseif (preg_match('/^-(\d+)$/',$rr,$mv)) {
      // End of file range
      if ($len == '') {
	return false;
      }
      array_push($res,array($len-intval($mv[1]),$len-1));
    } else {
      return '';
    }
    if ($len != '') {
      $i = count($res) - 1;
      if ($res[$i][0] >= $len) {
	return false;
      }
      if ($res[$i][1] != '' 
	  && ($res[$i][1] >= $len || $res[$i][1] < $res[$i][0])) {
	return false;
      }
    }
  }
  return $res;
}

function szfile($from) {
    $cpos = ftell($from);
    fseek($from,0,SEEK_END);
    $sz = ftell($from);
    fseek($from,$cpos,SEEK_SET);
    return $sz;
}

function dl_check($from,$cc,&$total_ln) {
  // The file still being downloaded
  //debug_msg("DLCHECK: lock $from");
  if (!flock($from,LOCK_EX|LOCK_NB)) {
    //debug_msg("DLCHECK: Thread running!");
    return true;
  }
  flock($from,LOCK_UN);
  //debug_msg("DLCHECK: ctl file $cc");
  if (!file_exists($cc)) {
    // Ooops...
    //debug_msg("DLCHECK: missing ctl file: $cc");
    if (!headers_sent()) {
      sendresp(500,"Background thread aborted");
    } else {
      //debug_msg("Background thread failed");
      die("We are in trouble");
    }
  }
  // Update the total file length...
  if ($total_ln == '') {
    $total_ln = szfile($from);
  }
  return false;
}

function myfpassthru($file) {
  while( (!feof($file)) && (connection_status()==0) ){
    set_time_limit(30);
    print(fread($file, FF_BUFSZ));
    flush();
  }
  if (connection_status()) {
    //debug_msg("Connection lost at ".ftell($file));
  }
}

function return_range($from,$cc,$total_ln,$start,$end) {
  if ($start) {
    while (dl_check($from,$cc,$total_ln) && szfile($from) < $start) {
      // If still downloading and file size is less than start...
      sleep(1);
    }
    if (szfile($from) < $start) {
      // Stop downloading and still file is not large enough!
      return "Fetcher aborted early";
    }
    fseek($from,$start,SEEK_SET);
  }
  // OK, at least the beginning of the file is there... we 
  // can start streaming...

  if ($end == '' && $total_ln != '') {
    $end = $total_ln-1;
  }
  if ($end != '') {
    // We can calculate range length...
    $range_ln = $end - $start +1;
  } else {
    $range_ln = '';
  }
  if (headers_sent()) {
    if ($range_ln != '') {
      echo("Content-Length: ".$range_ln.FF_CRLF);
    }
    if ($end != '' && $total_ln != '' && ($start > 0 && $end < ($total_ln-1))) {
      echo("Content-Range: ".$start."-".$end."/".$total_ln.FF_CRLF);
    }
    echo(FF_CRLF);
  } else {
    if ($range_ln != '') {
      header("Content-Length: ".$range_ln);
    }
    if ($end != '' && $total_ln != '' && ($start > 0 && $end < ($total_ln-1))) {
      header("Content-Range: ".$start."-".$end."/".$total_ln);
    }
  }
  // OK, start streaming stuff...
  while (true) {
    set_time_limit(30);	// Reset execution timer...
    while (dl_check($from,$cc,$total_ln) 
	   && szfile($from) <= ftell($from)+FF_BUFSZ) {
      sleep(1);
    }

    if ($end == '' && $total_ln != '') {
      $end = $total_ln-1;
    }
    /*
    if (file_exists($cc) && 
	($end == '' || ($total_ln != '' && $end = $total_ln - 1))) {
      // We can copy till EOF...
      debug_msg("DL Passthru: $uri (".ftell($from)."to $total_ln)");
      set_time_limit(30);	// Reset execution timer...
      myfpassthru($from);
      return '';
    }
    */

    if ($end == '') {
      $blksz = FF_BUFSZ;
    } else {
      $blksz = $end - ftell($from) + 1;

      if ($blksz > FF_BUFSZ) {
	$blksz = FF_BUFSZ;
      }
    }
    if ($blksz < 0) {
      return "READ PAST EOF?!?!?";
    } elseif ($blksz == 0) {
      // Reached the end...
      return '';
    }
    if (szfile($from) < ftell($from) + $blksz) {
      return "Fetcher aborted early";
    }
    
    $buf = fread($from,$blksz);
    if ($blksz != strlen($buf)) {
      echo "$blksz != ".strlen($buf)."\n";
      return "SHORT READ";
    }
    print($buf);
    flush();
  }
}

function v_expand($str,&$vars) {
  foreach ($vars as $vname => $vval) {
    $str = preg_replace('/\$'.$vname.'/',$vval,$str);
  }
  return $str;
}


header("Accept-Ranges: bytes");
umask(0002);
if (isset($_GET["repo"]) && isset($_GET["vhost"]) && isset($_GET["uri"])) {
  // FETCHER engine
  $repo = $_GET["repo"];
  $vhost = $_GET["vhost"];
  $uri = $_GET["uri"];
  
  if (strstr('/'.$vhost.'/',"/../") || strstr('/'.$uri.'/',"/../")) {
    sendresp(403,"Invalid string in query string");
  }

  $cached_file = $cf["cache_dir"].$vhost.'/files/'.$uri;
  $cached_hdr = $cf["cache_dir"].$vhost.'/hdrs/'.$uri;
  $control_file = $cf["cache_dir"].$vhost.'/priv/'.$uri;

  $fd = fopen($cached_file,"r+");
  if (!$fd) {
    sendresp(403,"Unable to cache $vhost:$uri");
  }
  ftruncate($fd,0);
  rewind($fd);

  //debug_msg("Grab file lock: $cached_file");
  flock($fd,LOCK_EX);
  //debug_msg("Got file lock");
  echo("OK\n");
  //debug_msg("Download $repo$uri started");
  flush();	// Signal partner that it can continue...
  //debug_msg("FLUSHED");
  ignore_user_abort(true);
  
  $start = microtime(true);
  while (true) {
    $hdr = fetch_dl($repo.$uri,$errno,$errstr,$fd);

    // Confirm the size of the file
    if (isset($hdr["Content-Length"])) {
      $len = $hdr["Content-Length"];
    } elseif (isset($_GET["len"])) {
      $len = $_GET["len"];
    } else {
      $len = -1;
    }

    if ($len != -1 && $len != ftell($fd)) {
      // Oh no... file did not download correctly .. RETRY!
      debug_msg("Download did not match content length: ".
		$len . " <=> ".ftell($fd));
      $repo = pick_rnd_vhost($vhost);
      ftruncate($fd,0);
      rewind($fd);
    } else {
      break;
    }
  }

  log_msg("proxy.log",implode(":",array($repo.$uri,
					(microtime(true) - $start),
					ftell($fd),
					fmtSzUnits(ftell($fd)))));
  echo "<pre>";
  if ($hdr === false) {
    echo("FETCH ERROR: ".ff_sock_strerror($errno)."($errno): $errorstr\n");
  } else {
    // Success!
    if (!isset($hdr["Content-Length"])) {
      // Weird if this ain't there!
      $hdr["Content-Length"] = ftell($fd);
    }
    file_put_contents($cached_hdr,serialize($hdr));
    //debug_msg("Writing $control_file");
    file_put_contents($control_file,
		      implode("\n",array($repo,$vhost,$uri,time())));

    print_r($hdr);
  }
  echo "</pre>";
  
} elseif (isset($_SERVER["PATH_INFO"])) {
  //
  // Retrieve the request details...
  //
  $uri = substr($_SERVER["PATH_INFO"],0,1) == "/" ?
    substr($_SERVER["PATH_INFO"],1) : $_SERVER["PATH_INFO"];
  if (strstr('/'.$uri,"/../")) {
    log_msg("access.log","[FORBIDDEN],bad chars");
    sendresp(403,"Invalid string in URI=$uri");
  }

  list($vhost,$uri) = explode("/",$uri,2);
  if ($_SERVER["REQUEST_METHOD"] == "HEAD") {
    $head_only = true;
  } elseif ($_SERVER["REQUEST_METHOD"] == "GET") {
    $head_only = false;
  } else {
    log_msg("access.log","[ERROR],bad request method ".
	    $_SERVER["REQUEST_METHOD"]);
    sendresp(403,"Invalid request method: ".$_SERVER["REQUEST_METHOD"]);
  }
  if (isset($_SERVER["HTTP_IF_MODIFIED_SINCE"])) {  
    $ifmosince = $_SERVER["HTTP_IF_MODIFIED_SINCE"];
  } else {
    $ifmosince = '';
  }
  if (isset($_SERVER['HTTP_IF_RANGE'])) {
    debug_msg("IF_RANGE: ".$_SERVER['HTTP_IF_RANGE']);
  }
  if (isset($_SERVER['HTTP_RANGE'])) {
    $rangereq  = $_SERVER['HTTP_RANGE'];
  } else {
    $rangereq = '';
  }

  $cache_status = '';
  foreach (array('HTTP_PRAGMA','HTTP_CACHE_CONTROL') as $tag) {
    if (isset($_SERVER[$tag])) {
      if (preg_match('/no-cache/',$_SERVER[$tag])) {
	$cache_status = 'EXPIRED';
      }
    }
  }

  if (!isset($cf["vhosts"][$vhost])) {
    log_msg("access.log","[NOTFOUND],vhost unknown");
    sendresp(404,"vhost=$vhost not defined");
  }

  $filename = basename($uri);
  if ($filename == "") {
    log_msg("access.log","[HIT],index");
    exit();
  }

  if (!is_static_file($filename) && !is_index_file($filename)) {
    log_msg("access.log","[FORBIDDEN],invalid file type");
    sendresp(403,"Invalid file type: \"$filename\"");
  }

  $cached_file = $cf["cache_dir"].$vhost.'/files/'.$uri;
  $cached_hdr = $cf["cache_dir"].$vhost.'/hdrs/'.$uri;
  $control_file = $cf["cache_dir"].$vhost.'/priv/'.$uri;

  foreach (array($cached_file,$cached_hdr,$control_file) as $cc) {
    $dn = dirname($cc);
    if (!is_dir($dn)) {
      if (!mkdir($dn,0777,true)) {
	log_msg("access.log","[ERROR],mkdir($dn)");
	sendresp(403,"System error: mkdir($dn)");
      }
    }
  }

  if (is_index_file($filename)) {
    if ($cache_status != 'EXPIRED' 
	&& file_exists($cached_file) && file_exists($cached_hdr)
	&& !$cf["offline"]) {
      if ($cf["expire_hours"] > 0) {
	$now = time();
	$mtime = filemtime($cached_file);
	if ($mtime && intval(($now-$mtime)/3600) > $cf["expire_houts"]) {
	  $cache_status = 'EXPIRED';
	} else {
	  // Use HTTP timestamping...
	  $hdr = fetch_hdr($vhost,$uri,$xhost,$errno,$errstr);

	  if ($hdr && substr($hdr[":status"],0,1) == '2' 
	      && isset($hdr["Last-Modified"])) {
	    $ohd = unserialize(file_get_contents($cached_hdr));
	    if (isset($ohd["Last-Modified"]) &&
		strtotime($ohd["Last-Modified"]) 
		>= strtotime($hdr["Last-Modified"])) {
	      // that's OK, file is up to date...
	    } else {
	      // Remote file is more up-to-date...
	      $cache_status = 'EXPIRED';
	    }
	  } else {
	    // Something happened...
	    $cache_status = 'OFFLINE';
	  }
	}
      }
    }
  }

  if ($ifmosince != '' && $cache_status != 'EXPIRED' 
      && file_exists($cached_hdr)) {
    $ohd = unserialize(file_get_contents($cached_hdr));
    if (isset($ohd["Last-Modified"]) && 
	strtotime($ifmosince) >= strtotime($ohd["Last-Modified"])) {

      log_msg("access.log","[IFMOD],not modified");
      sendresp(304,"Not Modified");
    }
  }
  grab_lock($vhost,"file download decision");
  //debug_msg("Enter download decision ($vhost)");

  $download = false;
  if (file_exists($cached_file) &&
      file_exists($cached_hdr) && 
      $cache_status == '') {
    //debug_msg("Check if file is cached...");
    $fromfile = fopen($cached_file,"r");
    if ($fromfile === false) {
      debug_msg("Unable to open cached file: $cached_file");
      die("Unable to open cached file: $cached_file");
    }
    if (file_exists($control_file)) {
      // Cool, cache hit!
      //debug_msg("Cache hit");
      $cache_status = 'HIT';
    } else {
      //A fetcher was either not sucesfull or is still running...
      // Look for activity
      if (flock($fromfile,LOCK_EX|LOCK_NB)) {
	flock($fromfile,LOCK_UN);
	// No fetcher working on this package. Redownload it.
	fclose($fromfile);
	$download = true;
	debug_msg("Fetcher not running... DOWNLOAD");
      } else {
	debug_msg("Another fetcher already running");
      }
    }
  } else {
    $download = true;
    debug_msg("Cache miss... DOWNLOAD");
  }
  if ($download) {
    //debug_msg("Check for offline");
    if ($cf["offline"]) {
      // Bypass for offline mode...
      release_lock($vhost);
      log_msg("access.log","[ERROR],internal error");
      sendresp(503,"Service not available: rpmgot is offline");
    }
    # (re) download them
    //debug_msg("(re)download stuff");

    @unlink($cached_file);
    @unlink($cached_hdr);
    @unlink($control_file);

    // Retrieve the header first...
    debug_msg("Retrieve header $xhost.$uri");
    $hurl = '';
    do {
      if ($hurl == '') {
	$hdr = fetch_hdr($vhost,$uri,$xhost,$errno,$errstr);
      } else {
	$hdr = fetch_dl($hurl,$errno,$errstr);
      }
      if (!$hdr 
	  || substr($hdr[":status"],0,1) == '4' 
	  || substr($hdr[":status"],0,1) == '5') {
	release_lock($vhost);
	sendresp($hdr[":status"],$hdr[":status-msg"]);
	log_msg("access.log","[ERROR],remote internal error");
      }
      if (substr($hdr[":status"],0,1) == '3') {
	if (isset($hdr["Location"])) {
	  $hurl = $hdr["Location"];
	} else {
	  release_lock($vhost);
	  log_msg("access.log","[ERROR],remote protocol error");
	  sendresp(500,"Missing location in redirection");
	}
	debug_msg("Redirection... $hurl");
      } else {
	$hurl = '';
      }
    } while ($hurl != '');

    // Make sure the file exists...
    debug_msg("Create header for $cached_hdr");
    foreach (array($cached_file,$cached_hdr) as $ff) {
      $fp = fopen($ff,"x+");
      if ($fp === false) {
	debug_msg("Unable to create new $ff");
	log_msg("access.log","[ERROR],file access");
	sendresp(500,"Unable to create cached file");
      }
    }
    file_put_contents($cached_hdr,serialize($hdr));

    if ($cache_status == '') {
      $cache_status = 'MISS';
    }

    // Start fetcher
    $ff = array("repo=".urlencode($xhost),
		"vhost=".urlencode($vhost),
		"uri=".urlencode($uri));
    if (isset($hdr["Content-Length"])) {
      array_push($ff,"len=".urlencode($hdr["Content-Length"]));
    }
    //debug_msg("Start fetcher! ".implode("&",$ff));
    $ff = fopen($cf["fetcher_url"].'?'.implode("&",$ff),"r");
    //debug_msg("fetcher started");    
    $fromfile = fopen($cached_file,"r");
    fclose($ff);
  }
  //debug_msg("release lock: $vhost");
  release_lock($vhost);

  if (file_exists($control_file) && isset($cf["cache_url"])) {
    // This file is complete!... We do a redirect and let the web
    // server stream it!
    log_msg("access.log","[HIT],lighttpd stream");
    // debug_msg("Cache HIT -- fastcopy, STATS [$cache_status]");
    header($_SERVER["SERVER_PROTOCOL"].' 302 Moved here');
    header("Location: http://".$_SERVER["SERVER_NAME"].$cf["cache_url"].
	   $vhost.'/files/'.$uri);
    exit();
  }
    

  // We are ready to sent the file back to client...
  $hdr = unserialize(file_get_contents($cached_hdr));
  foreach (array("Last-Modified","Content","Accept",
		 "ETag","Age","Content-Type") as $hh) {
    if (isset($hdr[$hh])) {
      header($hh.": ".$hdr[$hh]);
    }
  }
  if ($head_only) {
    if (isset($hdr["Content-Length"])) {
      header("Content-Length: ".$hdr["Content-Length"]);
    }
    exit(0);
  }

  if (isset($hdr["Content-Length"])) {
    $total_ln = $hdr["Content-Length"];
  } else {
    $total_ln = '';
  }
  $ranges = parse_range($rangereq,$total_ln);
  if ($ranges === false) {
    log_msg("access.log","[ERROR],range error");
    sendresp(416,"Requested range not possible");
  }

  //
  // Make sure we don't send any erros to the browser
  //
  if (ini_get('display_errors')) {
    ini_set('display_errors', false);
  }


  // We retrieve the file
  if ($ranges == '') {
    $ms = return_range($fromfile,$control_file,$total_ln,0,'');
    if ($ms != "") {
      log_msg("access.log","[ERROR],$ms");
      if (headers_sent()) {
	debug_msg("Bail-out: $msg");
	die("Bail-out!: $ms");
      } else {
	sendresp(500,"Download error: $ms");
      }
    }
  } else {
    foreach ($ranges as $rr) {
      $ms = return_range($fromfile,$control_file,$total_ln,$rr[0],$rr[1]);
      if ($ms != "") {
	log_msg("access.log","[ERROR],$ms");
	  
	if (headers_sent()) {
	  debug_msg("Bail-out: $ms");
	  die("Bail-out!: $ms");
	} else {
	  sendresp(500,"Download error: $ms");
	}
      }
    }
  }
  log_msg("access.log","[{$cache_status}]");
  // debug_msg("STATS $vhost $uri - [$cache_status]");
} else {
  //
  // Main page
  //
  if (isset($_GET['cmd'])) {
    if ($_GET['cmd'] == 'cfg') {
      if (isset($_GET['select'])) {
	$selector = $_GET['select'];
      } else {
	$selector = 'f';
      }
      header('Content-type: text/plain');
      echo "#\n";
      echo "# Cached repo yum configuration for $selector\n";
      echo "#\n";
      foreach ($cf['vhosts'] as $vh => $dd) {
	$selected = false;
	foreach ($dd["select"] as $tok) {
	  if ($tok == $selector) {
	    $selected = true;
	  }
	}
	if (!$selected) continue;

	if (isset($dd["vars"])) {
	  // Unroll the loop....
	  $vars = array();
	  foreach ($dd["vars"] as $vn => $vvs) {
	    if (count($vars)) {
	      $nvars = array();
	      foreach ($vvs as $vv) {
		foreach ($vars as $vset) {
		  $vset[$vn] = $vv;
		  array_push($nvars,$vset);
		}
	      }
	      $vars = $nvars;
	    } else {
	      foreach ($vvs as $vv) {
		array_push($vars,array($vn => $vv));
	      }
	    }
	  }
	} else {
	  $vars = array(array());
	}
	echo "# $vh\n";
	foreach ($vars as $attr) {
	  echo '['.$vh;
	  if (count($attr)) {
	    foreach ($attr as $a => $b) {
	      if (preg_match('/\//',$b)) {
		printf('-%x',crc32($b));
	      } else {
		echo '-'.$b;
	      }
	    }
	  }
	  echo "]\n";

	  echo 'name='.v_expand($dd['name'],$attr)."\n";
	  if (isset($dd['gpgkey'])) {
	    echo 'gpgkey='.v_expand($dd['gpgkey'],$attr)."\n";
	    echo "gpgcheck=1\n";
	  } else {
	    echo "gpgcheck=0\n";
	  }
	  echo "baseurl=".$cf["baseurl"].'/'.$vh;
	  echo v_expand($dd["baseurl"],$attr)."\n";
	  $ena= 1;
	  if (isset($dd['disabled'])) {
	    if ($dd['disabled']) {
	      $ena = 0;
	    }
	  }
	  echo "enabled=$ena\n";
	  echo "\n";
	}
      }
      // print_r($cf["vhosts"]);
    } else {
      ?>
      <html>
	<body>
	Invalid command <em><?=$_GET['cmd']?></em>
	</body>
      </html>
      <?php
    }
  } else {
    ?>
<html>
<head><title>RPMGOT</title></head>
<body>
  <h1>RPMGOT</h1>
  RPMGOT is a simple php based caching-proxy intented to be used to cache
  RPM repositories.

  It borrows heavily from pkg-cacher.
  uri fetching based on http://www.askapache.com/php/fsockopen-socket.html

  Internal links:
  <ul>
    <li><a href="<?=$cf['baseurl']?>">Base URL</a></li>
    <li>Config URLs:
      <ul>
      <?
      $selectors = array();
      foreach ($cf['vhosts'] as $vh => $dd) {
	foreach ($dd['select'] as $tok) {
	  $selectors[$tok] = $tok;
	}
      }
      foreach ($selectors as $n) {
	echo '<li><a href="'.$cf['baseurl'].'?cmd=cfg&select='.$n.'">';
	echo 'Config selector '.$n.'</a></li>';
      }
      ?>
      </ul>
    </li>
  </ul>

  <hr />
  <pre>
  TODO:
  - check server metadata info before expiring...
  - pkg expiration?
  - statistics
  - rewrite rules?
  - Add DL speed and size to DL spec
  </pre>
      <?php
      echo "<table border=1><tr><th>vhost</th><th>URLs</th></tr>\n";
      foreach ($cf["vhosts"] as $vh => $lst) {
	echo "<tr><td>".$vh."</td>";
	echo "<td><pre>";
	print_r($lst);
	print "</pre></td></tr>";
      }
      echo "</table>";
      ?>

</body>  
</html>
  <?php
    echo "<pre>\n";
      echo "_GET:\n";
      print_r($_GET);
      echo "_SERVER:\n";
      print_r($_SERVER);  
  }
}
?>