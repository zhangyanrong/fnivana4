#!/bin/sh
FILEPATH=$1;
if [[ ! -e ${FILEPATH} ]]; then
    FILEPATH=/mnt/logs/nginx/nirvana3.fruitday.com/v2;
fi
echo $FILEPATH;
TODAY=$(date +%Y_%m_%d);
LASTDAY=$(date -d last-day +%Y_%m_%d);

function compress() {
    FILEPATH=$1;
    DATE=$2;
    {
        if [[ -e ${FILEPATH}"/"${DATE} ]]; then
            rmdir ${FILEPATH}"/"${DATE};
        fi
    } || {
        NOW_HOUR=$(date +%H);
        for((i = 0; i < $NOW_HOUR; i++));
        do
            var=$(printf "%02d" "$i");
            filename=${FILEPATH}"/"${DATE}"/"${DATE}"-"$var;
            filename_info=${filename}"_INFO";
            filename_debug=${filename}"_DEBUG";
            filename_error=${filename}"_ERROR";
            real_compress $filename_info;
            real_compress $filename_debug;
            real_compress $filename_error;
        done
    }
}

function real_compress() {
    filename=$1;
    if [ -f "$filename" ]; then
        gzip $filename;
    fi
}

compress $FILEPATH $TODAY;
compress $FILEPATH $LASTDAY;