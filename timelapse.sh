#!/bin/bash

api_url="https://192.168.1.1:8079/screenshot"
api_username=""
api_password="pwd"

channel=AKfx10Oy

#example
# https://192.168.1.1:8079/screenshot/AKfx10Oy?timestamp=20190424T132001&password=pwd

d="2019-09-01T00:00:00+03:00"
enddate="2019-10-01T00:00:00+03:00"

while [[ "$d" < "$enddate" ]]; do
	echo $d
	timestamp=$(date +%Y%m%dT%H%M%S -d "$d")

	url=$api_url/$channel?timestamp=$timestamp\&password=$api_password
	
	#if [[ $d != *T1?:??* ]]; then
		#echo $url
		wget --no-check-certificate $url -O img/08/$timestamp.jpg
	#fi
	#d=$(date -Iseconds -d "$d + 1 second")
	d=$(date -Iminute -d "$d + 30 minute")
done

# ffmpeg -r 24 -f image2 -pattern_type glob -i 'img/*.jpg' out.mp4
# ffmpeg -f image2 -pattern_type glob -i 'img/0?/20190???T1*.jpg' -vf "scale=640:-1,format=yuv420p" -codec:v libx264 -framerate 24 -y out-d.mp4