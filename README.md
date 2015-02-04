# rpmgot

Software package download proxy

## Copyright

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

## Introduction

`rpmgot` is a simple/lightweight software package download proxy.  It
was designed to run on an OpenWRT router with some USB storage.  So it
is fully implemented as an `ash` script.

The basic idea has been implemented multiple times.  For example refer
to this
[article](http://ma.ttwagner.com/lazy-distro-mirrors-with-squid/) on a
[squid](http://www.squid-cache.org/) based implementation.

Unlike squid, which once you include all its dependancies can use up
over 1MB of space just to install it, this software has very small
dependancies.

The idea is for small developers running the same operating system
version(s) would benefit from a local mirror of them, but they
don’t have so many systems that it’s actually reasonable for them to run
a full mirror, which would entail rsyncing a bunch of content daily,
much of which may be packages would never be used.

`rpmgot` implements a “lazy” mirror — something that would appear to
its client systems as a full mirror, but would act more as a proxy. When a
client installed a particular version of a particular package for the
first time, it would go fetch them from a “real” mirror, and then
cache it for a long time. Subsequent requests for the same package
from the “mirror” would be served from cache.

The RPM files are cached for a very long time. Normally
it is an awful, awful idea for proxy servers to do interfere with the
Cache-Control / Expires headers that sites serve. But in the case of a
mirror, we know that any updates to a package will necessarily bump
the version number in the URL. Ergo, we can pretty safely cache RPMs
indefinitely.

## Requirements:

1. If using OpenWRT the default installation has pretty much
   everything that is needed.  You only need to add the `flock`
   package.
2. On a normal distribution, you need:
   - a shell (bash) or (ash)
   - flock and coreutils.
   - netcat
   - A web browser with cgi capability.

## Installation

The steps for installation are pretty simple.

1. Configure the web browser.
2. Copy `rpmgot.cgi` to the `cgi-bin` directory (or somewhere that
   can be called as a CGI script).
3. Create a directory to store cached files.  This needs to be
   writable by the web server user.
4. Configure `rpmgot.cgi` by editing the `CONFIGURATION`
   section. with values appropriate to your installation.

## Configuration

Configuration is achieved by editing the `rpmgot.cgi` file's
`CONFIGURATION` section.  The following variables are recognized:

- repos: space separated list of hosts that will be proxied.  If
  empty any host can be proxied.  Example:

    repos="ftp.nluug.nl mirror.centos.org elrepo.org"

- cache: path to a directory writeable by the web server user.  This
  is where the cached objects reside.
- file_types: List of file extensions that will be cached.  Example:

    file_types="rpm pkg.tar.xz"

## Usage

Once installed, you can use the proxy by using an url as follows:

    http://<proxy-server>/<cgi-bin-script>/<repo-host>/<path-to-system>

Where:

* proxy-server : the server where you configured your proxy.
* cgi-bin-script : path to your CGI script.  Usually:  
  `/cgi-bin/rpmgot.cgi`.
* repo-host : remote host that we are _mirroring_.
* path-to-system : file path to object.

For example, if you want to access the [CentOS](http://centos.org)
mirror at _ftp.nluug.nl_, point your browser to this URL:

    http://proxy-host/cgi-bin/rpmgot.cgi/ftp.nluug.nl/ftp/pub/os/Linux/distr/CentOS/

In order to use this with yum, you have to configure your repo
defintions in `/etc/yum.repos.d` by commenting out `mirrorlist` (with
`#`) and configuring `baseurl` with:

    baseurl=http://proxy-host/cgi-bin/rpmgot.cgi/ftp.nluug.nl/ftp/pub/os/Linux/distr/CentOS/$releasever/os/$basearch/

This is for the base repo.  Change other repo files accordingly.

The proxy only caches the objects that match the file extensions
defined in `file_types`.  Anything else is simply forwarded to the
remote host.

## Tips

The first connection to the proxy for a cacheable object, it will
download/stream the file to the client.

Subsequent requests will be fullfilled from the cache.  Use `wget`
command with the `-S` option to check downloaded headers:

    wget -S http://my-mirror.lan/cgi-bin/rpmgot.cgi/ftp.nluug.nl/ftp/pub/os/Linux/distr/fedora-epel/6/x86_64/munin-node-2.0.25-1.el6.noarch.rpm

## Missing features and limitations

- When multiple clients attempt to retrieve the same file at the same
  time, only the first one will succeed in obtaining a lock and the
  proxy will download and save the object.  Other clients will simply
  start downloading the file from the remote server directly.  Once
  the cacheable object is fully downloaded, any new requests for the
  same file will come from the cache.
- The proxy does not work as a repository for Anaconda installation.
  This is because Anaconda makes use of `Byte-Range` requests which
  are *not* supported by `rpmgot`.  The proxy does work quite well
  with `yum`.
- The on-disk format of the cache contains HTTP headers and the actual
  content.  The expectation is that files will be always be retrieved
  through http.  You can use `wget` to retrieve cached objects.
- It only supports the `HTTP GET` method.  Other methods like `POST`
  or `HEAD` are not implemented.  This is enough for `yum`.

## Design

The script is quite simple and relatively short.

It first check the `PATH_INFO` variable to determine the `remote_host`
and `remote_path`.

If makes sure that request is valid by checking if the method is `GET`
and the `remote_host` is in the allowed list.

It then checks if the request is for an object that can be cached
according to the `file_types` variable.  If it is not cacheable, it
will simply forward the request to the remote server.  This is done
using `netcat`.

If the object is cacheable then it will first try to lock it.  If the
lock is busy that means that another instance is currently busy
downloading the file and will simply forward the request to the remote
server for handling.

Since we obtained the lock, we will download the file from the remote
host while simultaneously streaming it to the client and saving the
file in the cache.

After that is completed, the file is checked to make sure that the
actual file size matches `Content-Length` from the request headers.
If the sizes do not match or if `Content-Length` was missing from the
headers, the file is deleted (assumed to be an incomplete download).

## History

- 1.0:  
  Rewritten in shell script.  First public release.
- 0.0:  
  Originally I wrote this code in PHP.  Obviously this added a PHP
  dependancy so I had to run this in a full server (not an OpenWRT
  router).  Figure that re-writing it in Shell script would make
  sense.

## TODO

- Cache expiration : delete old objects from the cache.
- Verify downloaded RPMs.  Using an external script:
  - Retrieve list of unverified cached objects.
  - Retrieve unverified cached objects and check them.
  - Delete objects or mark them as verified.
  - This requires an external script and for the `rpmgot.cgi` script
    to have a simple REST api to provide the list of unverified
    objects, delete objects and mark objects verified.
- Handle HEAD requests.
- Handle POST request as passthrough.
