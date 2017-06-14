<?php
#
#   rpmgot
#   Copyright (C) 2016 Alejandro Liu Ly
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
foreach ([$_SERVER['SCRIPT_FILENAME'],__FILE__,realpath(__FILE__)] as $f) {
  foreach ([preg_replace('/\.php/','',$f).'-cfg.php',dirname($f).'/config.php'] as $g) {
    if (is_file($g)) {
      require($g);
    }
  }
}
unset($f,$g);
////////////////////////////////////////////////////////////////////
//
// Handle defaults
//
if (!isset($cf)) sendresp(500,'Misconfigured script',FALSE);
$cferr = '';
if (!isset($_SERVER['SERVER_NAME'])) $_SERVER['SERVER_NAME'] = php_uname('n');
foreach ([
    // Base URL for the script
    'baseurl' => "http://".$_SERVER["SERVER_NAME"].'/'.$_SERVER["SCRIPT_NAME"],
    // Location of the cache directory
    'cache_dir' => 'rpmgot-cache/',
    // URL for the cache directory in redirections.  When the file is fully
    // download we simply do a HTTP redirect and let the web server handle it.
    'cache_url' => "http://".$_SERVER["SERVER_NAME"].dirname($_SERVER["SCRIPT_NAME"]).'/rpmgot-cache/',
    'vhosts' => FALSE,
    'cacheable_files' => [
	'\.rpm$',
	'\.pkg\.tar\.xz$',
    ],
    'meta_files' => [
	'\.db$',
	'\.db\.tar\.gz$',
	'\.files$',
	'\.files$\.tar\.gz$',
	'-filelists\.xml\.gz$',
	'-comps\.xml\.gz$',
	'-other\.xml\.gz$',
	'-primary\.xml\.gz$',
	'^repomd.xml$',
    ],
  ] as $k=>$v) {
  if (isset($cf[$k])) continue;
  if ($v === FALSE) {
    $cferr .= 'Configuration missing "'.$k.'" definition</br>';
    continue;
  }
  $cf[$k] = $v;
}
if ($cferr != '') sendresp(500,$cferr,FALSE);
// Fix-ups...
foreach (['cache_dir','cache_url'] as $k) {
  $cf[$k] = preg_replace('/\/+$/','',$cf[$k]).'/';
}
foreach (['cacheable_files','meta_files'] as $k) {
  if (is_array($cf[$k])) $cf[$k] = '/('.implode('|',$cf[$k]).')/';
}
foreach ([
    // max time for socket reads
    'FF_RECV_TIME' => 30,
    // max time for socket connect
    'FF_CONN_TIME' => 5,
    // Buffer size... increaing this should improve streaming performance
    // but chokes the lighttpd server...
    'FF_BUFSZ' => 1024 * 4,
    // Max re-directions
    'FF_MAX_REDIRS' => 16,
  ] as $k=>$v) {
  if (!defined($k)) define($k,$v);
}
define('FF_CRLF',chr(13).chr(10));

////////////////////////////////////////////////////////////////////
/*
 * Send error messages
 */
function sendresp($code,$msg='Internal Error',$logfile='error.log') {
  if ($logfile !== FALSE) log_msg($logfile,$msg);

  header($_SERVER['SERVER_PROTOCOL'].' '.$code.' '.$msg);
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
         <?php print_r($_SERVER); ?>
         </pre>
       </body>
    </html>
    <?php
  exit();
}
function log_msg($file,$msg) {
  global $cf;
  $fp= fopen($cf["cache_dir"].$file,"a");
  if (!$fp) return;
  if (flock($fp,LOCK_EX)) {
    fwrite($fp,date("Y-m-d H:i:s")." ".$_SERVER["REMOTE_ADDR"]." ".
	   $_SERVER["REQUEST_METHOD"]." ".
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
/* 
 * 
 * initiates the socket and download for the passed url.
 * uses the long2ip/ip2long for ip validation, uses gethostbyname to
 * get the ipv4 address which saves fsockopen from having to do the lookup
 * final data is saved to $rbody but currently only displays headers.
 */
function open_url($url,$method = 'GET'){
  $cnt = 0;
  while ($cnt < FF_MAX_REDIRS) { // In a loop to handle redirects...
    $ub = @parse_url($url);
    if(!isset($ub['host'])||empty($ub['host'])) {
      $_SERVER['tag'] = 'Empty HOST';
      return NULL;
    }

    $proto   = ($ub['scheme']=='https')?'ssl://':'';
    $port   = (isset($ub['port'])&&!empty($ub['port'])) ? $ub['port']:($proto!='')?443:80;
    $path   = (isset($ub['path'])&&!empty($ub['path'])) ? $ub['path']:'/';
    $query   = (isset($ub['query'])&&!empty($ub['query'])) ? '?'.$ub['query'] : '';
    $host   = $ub['host'];

    $fp = fsockopen($proto.$host,$port,$errno,$errstr,FF_CONN_TIME);
    if ($fp === FALSE) {
      $_SERVER['tag'] = 'FSOCKOPEN';
      return NULL;
    }
    stream_set_timeout($fp,FF_RECV_TIME);
      
    $request = join(FF_CRLF, [
		implode(' ',[$method,$path.$query,'HTTP/1.1']),
		'Host: '.$host,
		'User-Agent: RPMGOT Cacher',
		'Accept: */*',
		'Accept-Language: en-us,en;q=0.5',
		'Accept-Charset: utf-8;q=0.7,*;q=0.7',
		'Connection: close',
		'Referer: http://www.0ink.net/',
		'',
		'',
	       ]);

    // echo("REQUEST TO SEND:<pre>$request<pre>");
    // Send our request....
    if (fwrite($fp,$request) === FALSE) {
      $_SERVER['tag'] = 'send request';
      fclose($fp);
      return NULL;
    }

    // We want to get the header first...
    $hdr = [];

    while (($rbuf = fgets($fp)) !== FALSE) {
      $rbuf = trim($rbuf);
      if ($rbuf == '') break;

      if (!count($hdr)) {
	// Status response
	$hdr[''] = $rbuf;
	list($proto,$code,$msg) = preg_split('/\s+/',$rbuf,3);
	$hdr[':proto'] = $proto;
	$hdr[':status'] = $code;
	$hdr[':status-msg'] = $msg;
      } else {
	list($l,$r) = explode(':',$rbuf,2);
	$l = trim($l);
	$r = trim($r);
	if (isset($hdr[$l])) {
	  if (is_array($hdr[$l])) {
	    $hdr[$l][] = $r;
	  } else {
	    $hdr[$l] = [ $hdr[$l], $r ];
	  }
	} else {
	  $hdr[$l] = $r;
	}
      }
    }

    // Succes!!
    if ($hdr[':status']{0} == '2') {
      $hdr[':fp'] = $fp;
      return $hdr;
    }
    fclose($fp);
    // Not 2xx nor 3xx status code
    if ($hdr[':status']{0} != '3') {
      $_SERVER['tag'] = 'STATUS: '.$hdr[''];
      return NULL;
    }
    
    // Missing Location...
    if (!isset($hdr['Location'])) {
      $_SERVER['tag'] = 'Missing location in redirect';
      return NULL;
    }
    $url = $hdr['Location'];
    ++$cnt;
  }
  // Too many redirects!
  $_SERVER['tag'] = 'Too many redirects';
  return NULL;
}

function ff_tee(&$ifp,&$ofp) {
  while( (!feof($ifp)) && (connection_status()==0) ){
    set_time_limit(30);
    $buf = fread($ifp,FF_BUFSZ);
    if ($buf == FALSE) break;

    fwrite($ofp,$buf);
    echo($buf);
    flush();
  }
}

function out_header($h) {
  foreach ($h as $i=>$j) {
    if ($i == '') {
      header($j);
    } else {
      if ($i{0} == ':') continue;
      if (is_array($j)) {
	$replace = TRUE;
	foreach ($j as $k) {
	  header($i.': '.$k,$replace);
	  $replace = FALSE;
	}
      } else {
	header($i.': '.$j);
      }
    }
  }
}

function wr_header($f,$h) {
  $fp = fopen($f,'w');
  if ($fp === FALSE) return FALSE;
  foreach($h as $i=>$j) {
    if ($i == ':fp') continue;
    if (is_array($j)) {
      foreach ($j as $k) {
	fwrite($fp,$i.': '.$k.PHP_EOL);
      }
    } else {
      fwrite($fp,$i.': '.$j.PHP_EOL);
    }
  }
  fclose($fp);
  return TRUE;
}
function rd_header($f) {
  $fp = fopen($f,'r');
  if ($fp === FALSE) return FALSE;
  $hdr = [];
  while (($ln = fgets($fp)) !== FALSE) {
    list($l,$r) = preg_split('/: /', trim($ln),2);
    if (isset($hdr[$l])) {
      if (is_array($hdr[$l])) {
	$hdr[$l][] = $r;
      } else {
	$hdr[$l] = [ $hdr[$l], $r ];
      }
    } else {
      $hdr[$l] = $r;
    }
  }
  fclose($fp);
  return $hdr;
}

function listFolderFiles($dir,$mm){
  $list = [];
  foreach (scandir($dir) as $ff ) {
    if ($ff == '.' || $ff == '..') continue;
    if (preg_match($mm,$ff) && is_file($dir.$ff.'/'.$ff)) {
      $list[] = $dir.$ff;
    }
    if (is_dir($dir.$ff))
      $list = array_merge($list,listFolderFiles($dir.$ff.'/',$mm));
  }
  return $list;
}

////////////////////////////////////////////////////////////////////

if (PHP_SAPI == 'cli') {
  define('CMDNAME',array_shift($argv));
  switch(array_shift($argv)) {
    case 'info':
      print_r($cf);
      print_r($_SERVER);
      print_r($argv);
      break;
    case 'clean':
      $cache_dir = realpath(dirname($_SERVER['PHP_SELF'])).'/'.$cf['cache_dir'];

      // Check for archlinux repos...
      foreach (listFolderFiles($cache_dir,'/\.db$/') as $repo) {
	$rname = basename($repo);
	$repo = dirname($repo).'/';
	echo "REPO: $repo NAME: $rname\n";
	$pkgs = [];
	foreach (array_map('basename',glob($repo.'/*.pkg.tar.xz')) as $p) {
	  $pkgs[$p] = $p;
	}
	echo "Pkgs: ".count($pkgs)."\n";
	$fp = gzopen($repo.$rname.'/'.$rname,'r');
	while (($ln = fgets($fp)) !== FALSE) {
	  $ln = trim($ln);
	  if (preg_match('/\.pkg\.tar\.xz$/',$ln)) {
	    if (isset($pkgs[$ln])) unset($pkgs[$ln]);
	  }
	}
	gzclose($fp);
	echo "Expired Pkgs: ".count($pkgs)."\n";
	print_r($pkgs);
      }
      break;
    default:
      die('Invalid sub-command'.PHP_EOL);
  }
  exit;
}


////////////////////////////////////////////////////////////////////


$method = $_SERVER['REQUEST_METHOD'];
if ($method != 'GET' && $method != 'HEAD') sendresp(405,'Invalid request method '.$method);


if (!isset($_SERVER['PATH_INFO']) || !$_SERVER['PATH_INFO']) {
  ?>
  <html>
    <head>
      <title>RPMGOT</title>
    </head>
    <body>
      <h1>RPMGOT</h1>
      RPMGOT is a simple php based caching-proxy intented to be used to cache
      software repositories.
      <hr/>
      <h2>Available Repos</h2>
      <ul>
	<?php
	  foreach ($cf['vhosts'] as $k=>$v) {
	    echo '<li><a href="'.$cf['baseurl'].'/'.$k.'/">'.$k.'</a></li>';
	  }
	?>
      </ul>
    </body>
  </html>
  <?php
  exit;
}

// Validate paths...
$path_info = $_SERVER['PATH_INFO'];
$remote_path = FALSE;
foreach ($cf['vhosts'] as $k => $v) {
  if (substr($path_info,0,strlen($k)+2) == '/'.$k.'/') {
    $remote_path = $v;
    $path_info = '/'.substr($path_info,strlen($k)+2);
    $vhost = $k;
    break;
  }
}

if ($remote_path === FALSE) sendresp(403,'Invalid path: '.$path_info);

$fname = basename($path_info);
if (preg_match($cf['cacheable_files'],$path_info)) {
  $fpath = $cf['cache_dir'].$vhost.$path_info.'/';
  if (is_file($fpath.$fname)) {
    // File already exists...
    $h = rd_header($fpath.'hdr');
    if ($h === FALSE) sendresp(500,'Error reading header: '.$fpath);

    log_msg('access.log','cache-hit');
    out_header($h);
    readfile($fpath.$fname);
  } else {
    $h = open_url($remote_path.$path_info, $method);
    if ($h === NULL) sendresp(500,'Error accessing '.$remote_path.$path_info);
    if ($method == 'HEAD') {
      log_msg('access.log','caching');
      out_header($h);
    } else {
      if (!is_dir($fpath)) {
	if (!mkdir($fpath,0777,TRUE)) sendresp(500,'mkdir error: '.$fpath);
      }
      $lock = fopen($fpath.'lock','c');
      if ($lock === FALSE) sendresp(500,'Unable to get lock: '.$fpath);
      if (!flock($lock,LOCK_EX|LOCK_NB)) {
	// Unable to obtain lock
	log_msg('access.log','cacheable-passthru');
	out_header($h);
	fpassthru($h[':fp']);
      } else {
	// Fetch file and save it...
	if (wr_header($fpath.'hdr',$h) === FALSE) sendresp(500,'write header: '.$fpath);
	$ofp = fopen($fpath.'tmp','wb');
	if ($ofp == FALSE) sendresp(500,'fopen-tmp: '.$fpath);
	log_msg('access.log','cacheable');
	out_header($h);
	ff_tee($h[':fp'],$ofp);
	if (isset($h['Content-Length'])) {
	  $csize = ftell($ofp);
	  if ($csize != $h['Content-Length']) {
	    // File size did not match!
	    unlink($fpath.'tmp');
	    fclose($ofp);
	    exit;
	  }
	}
	if (isset($h['Content-Length'])) {
	  $csize = ftell($ofp);
	  if ($csize != $h['Content-Length']) {
	    // File size did not match!
	    unlink($fpath.'tmp');
	    fclose($ofp);
	    exit;
	  }
	}
	fclose($ofp);
	rename($fpath.'tmp',$fpath.$fname);
      }
    }
  }
} else {
  $h = open_url($remote_path.$path_info, $method);
  if ($h === NULL) sendresp(500,'Error accessing (P) '.$remote_path.$path_info);

  if (preg_match($cf['meta_files'],$path_info)) {
    if ($method == 'GET') {
      $fpath = $cf['cache_dir'].$vhost.$path_info.'/';
      if (!is_dir($fpath)) {
	if (!mkdir($fpath,0777,TRUE)) sendresp(500,'mkdir error: '.$fpath);
      }
      $lock = fopen($fpath.'lock','c');
      if ($lock === FALSE) sendresp(500,'Unable to get lock: '.$fpath);
      if (!flock($lock,LOCK_EX|LOCK_NB)) {
	// Unable to obtain lock
	log_msg('access.log','meta-passthru');
	out_header($h);
	fpassthru($h[':fp']);
      } else {
	$ofp = fopen($fpath.'tmp','wb');
	if ($ofp == FALSE) sendresp(500,'fopen-tmp: '.$fpath);
	log_msg('access.log','meta');
	out_header($h);
	wr_header($fpath.'hdr',$h); // Not really needed...
	ff_tee($h[':fp'],$ofp);
	if (isset($h['Content-Length'])) {
	  $csize = ftell($ofp);
	  if ($csize != $h['Content-Length']) {
	    // File size did not match!
	    unlink($fpath.'tmp');
	    fclose($ofp);
	    exit;
	  }
	}
	fclose($ofp);
	unlink($fpath.$fname);
	rename($fpath.'tmp',$fpath.$fname);
      }
    } else {
      log_msg('access.log','meta');
      out_header($h);
    }
  } else {
    log_msg('access.log','passthru');
    out_header($h);
    fpassthru($h[':fp']);
  }
  fclose($h[':fp']);
}
