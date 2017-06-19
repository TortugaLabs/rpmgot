<?php
#
# Configurable settings
#
$cf = [
  // Location of the cache directory
  'cache_dir' => 'rpmgot-cache/',
  // URL for the cache directory in redirections.  When the file is fully
  // download we simply do a HTTP redirect and let the web server handle it.
  'cache_url' => dirname($_SERVER['SCRIPT_NAME']).'/rpmgot-cache/',

  'vhosts' => [
    'centos' => 'http://ftp.nluug.nl/os/Linux/distr/CentOS',
    'nux/dextop' => 'http://li.nux.ro/download/nux/dextop',
    'epel' => 'http://ftp.nluug.nl/pub/os/Linux/distr/fedora-epel',
    'remirepo' => 'http://rpms.remirepo.net/enterprise',
    'elrepo' => 'http://elrepo.org/linux',
    'google' => 'http://dl.google.com/linux',
    'archlinux' => 'http://ftp.nluug.nl/pub/os/Linux/distr/archlinux',
  ],
  //'baseurl' => "http://".$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"],
  // URL to start a fetcher task to download from the upstream server
  // Needed because we can't rely on "fork" being available.
  //'fetcher_url' => "http://".$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"],
];
// max time for socket reads
//define('FF_RECV_TIME', 30);
// max time for socket connect
//define('FF_CONN_TIME', 5);
// Buffer size... increaing this should improve streaming performance
// but chokes the lighttpd server...
//define('FF_BUFSZ',1024*4);
