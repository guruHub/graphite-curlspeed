#!/bin/bash
#
#
# Source config
#
source config


# There are good chances if frequency is short and sites list big of 
# being overrun, so we place a lock to avoid such scenario.
# If this is the case, you will notice periods in graphtie without
# data.
if [ -f $LOCK ]; then
	echo "Error: lock file found. Is another instance still running?"
	echo "If not, fix it by: rm -f $LOCK"
	exit 1
fi

if [ "$TYPE" == "slave" ]; then
	# Slave should update websites list
	$CURLBIN -o sites.txt $SITES
	FILE="sites.txt"
else
	FILE=$SITES
fi

if [ ! -f $FILE ]; then
	echo "Error: Sites list file does not exist"
	exit 1
fi

if [ ! -f $DICTFILE ]; then
	echo "Error: Dictionary file does not exist"
	exit 1
fi

# Function to translate websites into nice names for graphite
function site2metric() {
	local this_site=$1

	# If site ends in /, remove it for graphtie
	if [[ $this_site =~ /$ ]]; then
		this_site=`echo $this_site |sed "s/\(.*\)\//\1/g"`
	fi

	# Reduce domain name if possible
	# Eg. if starts with www* remove it.
	this_site=`echo $this_site | sed "s/^www\.\(.*\)$/\1/g"`

	# Replace / into _
	this_site=`echo $this_site | sed "s/\//_/g"`
	# Replace . into _
	this_site=`echo $this_site | sed "s/\./_/g"`
	
	
	SITE_GRAPHITE=$this_site
}

# Loop for each website...
for SITELINE in `cat $FILE|grep -v ^\#`; do

	TIMESTAMP=`date +%s`

	# Split KEY & SITE from SITELINE
	KEY=`echo $SITELINE | awk -F '|' '{ print $1 }'`
	SITE=`echo $SITELINE | awk -F '|' '{ print $2 }'`
	
	# Some sites need a random number or word
	if [[ $SITE =~ CURLSPEED_RANDOM ]]; then
		SITE=`echo $SITE |sed "s/CURLSPEED_RANDOM_NUMBER/$RANDOM/g"`
		# As random word is more expensive, only if needed should be generated
		if [[ $SITE =~ CURLSPEED_RANDOM_WORD ]]; then
			# Generate a random word from linux dictionary
			DICTLENGTH=`awk 'NF!=0 {++c} END {print c}' $DICTFILE`
			WORDNUM=$((RANDOM%DICTLENGTH+1))
			RANDOM_WORD=`sed -n "$WORDNUM p" $DICTFILE`
			SITE=`echo $SITE |sed "s/CURLSPEED_RANDOM_WORD/$RANDOM_WORD/g"`
		fi
	fi

	# Prepend configured value to key if any
	if [ "$GRAPHITE_PREPEND"  != "" ]; then
		KEY="${GRAPHITE_PREPEND}.$KEY"
	fi
	
	# Add location if any to key
	if [ "$LOCATION" != "" ]; then
		KEY="${KEY}.$LOCATION"
	fi	
	
	SITE_RESULT=`curl -o /dev/null -s -w "%{time_namelookup};%{time_connect};%{time_starttransfer};%{time_total};%{size_download}\n" "$SITE"`;
	i=1
	
	# Push metric to queue
	for name in time_namelookup time_connect time_starttransfer time_total size_download; do
		filter='{ print $'$i' }'
		value=`echo $SITE_RESULT| awk -F ';' "$filter" `
		QUEUE="$QUEUE\n$KEY.$name $value $TIMESTAMP"
		i=$(( $i + 1 ))
		if [ "$name" == "time_starttransfer" ]; then
			tst=$value
		elif [ "$name" == "time_total" ]; then
			transfer_time=`echo "$value - $tst"|bc`
			if [[ $transfer_time < 1 ]]; then
				transfer_time="0"$transfer_time
			fi
			QUEUE="$QUEUE\n$KEY.time_downloading $transfer_time $TIMESTAMP" 
		fi
	done	
done

sites=`cat $FILE|grep -v ^\#|wc -l`
queued=`echo -e $QUEUE|wc -l`
#echo "Queued $queued metrics after checking $sites sites, now sending to graphite"

echo -e "$QUEUE" | nc $GRAPHITE_HOST $GRAPHITE_PORT

# Done!, remove lock.
rm -f $LOCK

