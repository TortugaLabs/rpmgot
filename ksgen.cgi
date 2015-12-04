#!/bin/sh
template_dir=/data/v1/www/centos-7/ks-tmpl
###########################################################################
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
parse_arg() {
  local n="$*"
  if ! $(echo "$n" | grep -q =) ; then
    echo "$vars"
    return
  fi
  local l=$(echo "$n" | cut -d= -f1 | tr a-z A-Z | tr -d ' 	')
  local r=$(echo "$n" | cut -d= -f2- | tr -d ' 	')
  [ -n "$vars" ] && echo "$vars"
  echo "s/<$l>/$r/"
}


[ ! -d $template_dir ] \
  && error 500 "Server error" "ksgen script misconfigured" \
  "You must define a valid template_dir"

[ -z "$PATH_INFO" ] \
  && error 403 Forbidden "No PATH_INFO specified" "Invalid path"

oIFS="$IFS"
IFS="/"
set - $PATH_INFO
IFS="$oIFS"

tmpl=""
vars=""

for i in "$@"
do
  [ -z "$i" ] && continue
  case "$i" in
    ks=*)
      tmpl=${i#ks=}
      [ ! -f $template_dir/$tmpl ] \
	&& error 404 Missing "Template not found" "Specified KS template does not exist!"
      ;;
    *)
      vars="$(parse_arg "$i")"
  esac
done

(
  echo Content-type: text/plain
  echo ''
  if [ -z "$vars" ] ; then
    cmd="cat"
  else
    cmd="sed $vars"
  fi
  set -x
  $cmd < $template_dir/$tmpl
) | unix2dos
