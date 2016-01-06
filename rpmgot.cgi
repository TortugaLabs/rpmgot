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
repos="ftp.nluug.nl li.nux.ro"
# List of hosts that we want to proxy to
cache="/data/v1/www/rpmgot"
# Path to a writeable directory where to store the cached objects
file_types="rpm"
# File type extensions that we want to cache
timeout=3600
# Clean download process after this many seconds
#
# END OF CONFIGURATION SECTION
######################################################################

valid_host() {
  local h
  [ -z "$repos" ] && return 0

  for h in $repos
  do
    [ x"$h" = x"$1" ] && return 0
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

cacheable() {
  local ext=
  for ext in $file_types
  do
    ( echo "$1" | grep -q '\.'"$ext"'$') && return 0
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
    echo Status: $code | unix2dos
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

valid_host $remote_host || \
  error 403 Forbidden "remote host not allowed" "$remote_host not in list"

cacheable $remote_path || passthrough $remote_host $remote_path

objpath=$remote_host/$remote_path
objname=$(basename $remote_path)

mkdir -p $cache/$objpath || passthrough $remote_host $remote_path
exec 9>$cache/$objpath/lock

# If we cannot obtain the lock that means another instance
# is downloading this file, because we do not know how to mux
# it, we just act as passthrough
flock -n 9 || passthrough $remote_host $remote_path

if [ -f $cache/$objpath/$objname ] ; then
  # This object already exists in the cache!
  exec 9>&-
  exec cat $cache/$objpath/$objname
fi

# Doesn't exist just yet.
# Download while saving the file.
( passthrough $remote_host $remote_path ) | tee $cache/$objpath/$objname

# We should at least check if we retrieved the full file...
ck_file $cache/$objpath/$objname && exit

# Failed...
rm -f $cache/$objpath/$objname
