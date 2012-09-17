graphite curlspeed
==================

About this script
------------------

This script will use curl to get speed information for a given list of
sites and submit the results to graphite.

This script is still a work in progress.

Author: Guzman Braso - guruhub.com.uy

Multi Node Setup
----------------

It was designed to be multi node so one can check speed from different
locations without having to maintain the websites list on each location.

You should choose a location to be your master installation and all others
to be slave of it. Masters will take website list from a file 'sites.txt'
and config from a file "config". Slaves will call an url to download sites
from master and have a local config.

So keep in mind that your master sites.txt should be reachable by your slaves 
and it's up to you to configure a way to access sites.txt in your server. 
This script does not need to be called remotely, only that file.

Requirements
------------
* Linux shell access (no root required)
* A word dictionary (sudo apt-get install wamerican-huge)
* Internet access to sites to measure.
* Curl binary (apt-get install curl)
* bc - An arbitrary precision calculator language (apt-get install bc)


Features
--------

* Random word & number support

Url's that contains CURLSPEED_RANDOM_WORD or CURLSPEED_RANDOM_NUMBER will
be submitted literally to graphite but will be replaced by a random word
or number before calling it with curl. This is useful to fool the cache.

* All possible combination of schedule runs are possible. It's you and crontab.

* Site list maintained at one location only.

Configuration
-------------

Two examples comes with the script as master or slave, copy the one
you want into a file named "config" and if slave change to the right
url the SITES variable. 

You need to add to crontab for the frequency you want, keep in mind a lower frequency
will mean your list of sites should be compact to be able to finish before fired again.

Example crontab line to run each minute with "www-data" user on /usr/local/src/graphite-curlspeed
```
* *	* * *	www-data	cd /usr/local/src/graphite-curlspeed && ./run.sh
```
 
TODO
----

* Paralell support

There is a clear bottleneck when (site_amount * site_time_average) > script_frequency.
Two way outs are rewritting it non blocking (events) or by forking checks and taking
care of not putting too much stress to the server.

* Git Support

Allow as alternative to web the use of git repository as source for config & sites.
As repos would be private master will need to support ssh key configuration to be able
to commit to repo. Slaves could be fine with https password access.

* Support more tools to measure speed (pagespeed, etc).

* Support for slaves to safely retrieve config from masters.

Contribute
----------

Fork the repo, apply your changes and submit a pull request. 

Approved pull requests commiters would be eligible for a free beer if ever come
to Uruguay.

License
-------

´´´
-----------------------------------------------------------------------------------
"THE BEER-WARE LICENSE" (Revision 42):
<guzman /AT/ guruhub.com.uy> wrote this file. As long as you retain this notice 
you can do whatever you want with this stuff. If we meet some day, and you think 
this stuff is worth it, you can buy me a beer in return. Guzmán Brasó
------------------------------------------------------------------------------------
´´´
