#!/bin/sh
#
# RPMGOT
#
# Software package download proxy
#
#    This program is free software: you can redistribute it and/or modify
#    it under the terms of the GNU General Public License as published by
#    the Free Software Foundation, either version 2 of the License, or
#    (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU General Public License for more details.
#
#    You should have received a copy of the GNU General Public License
#    along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#######################################################################
#
# CONFIGURATION:
#
# Customize the following variables to your liking
#
# List of hosts that we want to proxy to
repos="centos:ftp.nluug.nl/os/Linux/distr/CentOS"
repos="$repos nux/dextop:li.nux.ro/download/nux/dextop"
repos="$repos epel:ftp.nluug.nl/pub/os/Linux/distr/fedora-epel"
repos="$repos archlinux:ftp.nluug.nl/pub/os/Linux/distr/archlinux"
#repos="centos:ftp.nluug.nl/os/Linux/distr/CentOS nux/dextop:li.nux.ro/download/nux/dextop epel:mirror.proserve.nl/fedora-epel"
#repos="centos:mirror.colocenter.nl/pub/centos nux/dextop:li.nux.ro/download/nux/dextop epel:mirror.proserve.nl/fedora-epel"
#repos="centos:mirror.1000mbps.com/centos "
#repos="centos:ftp.tudelft.nl/centos.org"
#repos="centos:mirror.1000mbps.com/centos"
#repos="nux/dextop:li.nux.ro/download/nux/dextop li.nux.ro"

# Path to a writeable directory where to store the cached objects
cache="/data/v1/www/rpmgot"
# File type extensions that we want to cache
file_types="rpm pkg.tar.xz"
# File type extensions that we would like to save (not cache)
ftrace_types="db"
# Clean download process after this many seconds
timeout=3600
#
# END OF CONFIGURATION SECTION
######################################################################
#set -x
debug() {
  logger "$@"
}


valid_host() {
  local hpair
  [ -z "$repos" ] && return 0

  for hpair in $repos
  do
    if ! (echo "$hpair" | grep -q ':') ; then
      # No translation...
      [ x"$hpair" = x"$1" ] && return 0
      continue
    fi
    local src="$(echo "$hpair" | cut -d: -f1)"
    local dst="$(echo "$hpair" | cut -d: -f2-)"

    if ! (echo "$src" | grep -q '/') ; then
      # Translate only host part...
      local srchost="$src"
      local srcpath=""
    else
      # Translate host and path
      local srchost="$(echo "$src" | cut -d/ -f1)"
      local srcpath="$(echo "$src" | cut -d/ -f2-)"
    fi

    [ x"$srchost" != x"$1" ] && continue

    if ! (echo "$dst" | grep -q '/') ; then
      # Translate only host part...
      local dsthost="$dst"
      local dstpath=""
    else
      # Translate host and path
      local dsthost="$(echo "$dst" | cut -d/ -f1)"
      local dstpath="$(echo "$dst" | cut -d/ -f2-)"
    fi

    remote_host="$dsthost"
    if [ -n "$srcpath" ] ; then
      srcpath="$srcpath/"
      local ln=$(expr length "$srcpath")
    else
      local ln=0
    fi
    if [ x"$(expr substr "$remote_path" 1 $ln)" = x"$srcpath" ] ; then
      local j=$(expr $(expr length "$remote_path") - $ln)
      ln=$(expr $ln + 1)
      remote_path="$dstpath/$(expr substr "$remote_path" $ln $j)"
    fi
    #debug "XL: $remote_host $remote_path"
    return 0
  done
  return 1
}

unix2dos() {
  sed -e 's/$/\r/' "$@"
}
error() {
  logger "ERR: $*"

  local code="$1" reason="$2" msg="$3" ; shift 3
  unix2dos <<-EOF
	Content-type: text/html
	Status: $code $reason

	<html>
	 <head><title>Error: $msg</title></head>
	 <body>
	 <h1>Error: $msg</h1>
	 $*
	 </body>
	</html>
	EOF
  exit
}

is_file_type() {
  local ext= f="$1"
  shift
  for ext in "$@"
  do
    ( echo "$f" | grep -q '\.'"$ext"'$') && return 0
  done
  return 1
}

passthrough() {
  local \
    r_host="$1" \
    r_path="$2" \
    r_port="$3"
  [ -z "$r_port" ] && r_port=80
  local q
  [ -n "$QUERY_STRING" ] && q='?'

  (
    echo "$REQUEST_METHOD /$r_path$q$QUERY_STRING HTTP/1.0"
    echo Host: $r_host

    for p in \
      Accept Accept-Charset Accept-Encoding Accept-Language \
      Cookie Referer User-Agent Content-Type Content-Length
    do
      v=HTTP_$(echo $p | tr a-z- A-Z_)
      eval v=\"\$$v\"
      [ -n "$v" ] && echo "$p: $v"
    done
    echo ''
  ) | (
    mypid=$(sh -c 'echo $PPID')
    (
      exec >/dev/null </dev/null 2>&1
      cnt=$timeout
      while [ $cnt -gt 0 ]
      do
	sleep 1
	cnt=$(expr $cnt - 1)
	[ ! -d /proc/$mypid ] && exit
      done
      kill $mypid 2>/dev/null
    ) &
    child=$!
    echo $child
    exec nc "$r_host" $r_port
  )| (
    read mon
    read proto code msg
    #debug proto=$proto code=$code msg=$msg
    [ -z "$code" ] && code="500"
    if [ "$code" != "200" ] ; then
      error $code "$msg" "$msg" "pass through"
    fi
    cat
    kill $mon >/dev/null 2>&1
  )
  exit
}

content_length() {
  dd if=$1 bs=512 count=16 2>/dev/null | (
    while read a b c
    do
      [ x"$a" != x"Content-Length:" ] && continue
      echo $b | tr -dc 0-9
      break
    done
  )
}

ck_file() {
  local f="$1"

  local \
    clen=$(content_length $f) \
    flen=$(ls -l $f | awk '{print $5}')

  [ -z "$clen" ] && return 1

  [ $flen -lt $clen ] && return 1
  local hdr=$(expr $flen - $clen)

  echo "$(dd if=$f bs=$hdr count=1 2>/dev/null)" \
    | tr -d "\r" \
    | sed -e 's/^/:/' \
    | (
    lines=0
    last="no"
    while read x
    do
      last=no
      [ x"$x" != x":" ] && continue
      lines=$(expr $lines + 1)
      last=yes
    done
    if [ $last = yes -a $lines -eq 1 ] ; then
      exit 0
    fi
    exit 1
  )
  return $?
}

[ -z $PATH_INFO ] && \
  error 403 Forbidden "No PATH_INFO specified" "No valid path"

[ x"$REQUEST_METHOD" != x"GET" ] && \
  error 403 Forbidden "Unsupported method" "Method $REQUEST_METHOD unsupported"

remote_host=$(echo "$PATH_INFO" | cut -d/ -f2)
remote_path=$(echo "$PATH_INFO" | cut -d/ -f3-)
objpath=$remote_host/$remote_path


valid_host $remote_host || \
  error 403 Forbidden "remote host not allowed" "$remote_host not in list"

# debug "RPMGOT: $remote_host $remote_path"

# Handle non cacheable files...
if ! is_file_type $remote_path $file_types ; then
  is_file_type $remote_path $ftrace_types || passthrough $remote_host $remote_path
  objdir=$(dirname $objpath)
  mkdir -p $cache/$objdir || passthrough $remote_host $remote_path
  # Remember this object
  ( passthrough $remote_host $remote_path ) | tee $cache/$objpath
  exit 0
fi

objname=$(basename $objpath)

mkdir -p $cache/$objpath || passthrough $remote_host $remote_path
exec 9>$cache/$objpath/lock

# If we cannot obtain the lock that means another instance
# is downloading this file, because we do not know how to mux
# it, we just act as passthrough
flock -n 9 || passthrough $remote_host $remote_path
if [ -f $cache/$objpath/$objname ] ; then
  # This object already exists in the cache!
  # Make sure it is any good...
  if ck_file $cache/$objpath/$objname ; then
    exec 9>&-
    exec cat $cache/$objpath/$objname
  fi
  # Will try to download again...
  rm -f $cache/$objpath/$objname
fi

# We haven't seen this before
# Download while saving file
( passthrough $remote_host $remote_path ) | tee $cache/$objpath/$objname

# We should at least check if we retrieved the full file...
ck_file $cache/$objpath/$objname && exit

# Failed...
rm -f $cache/$objpath/$objname
