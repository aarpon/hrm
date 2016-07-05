#!/bin/bash

# exit on any error:
set -e

# remember path prefix as called, source functions
PFX="$(dirname $0)"
source "$PFX/functions.inc.sh"

EXP_SCRIPT="expect_scripts/hucore__preview_faba128_abspath.exp"

########## TEST DESCRIPTION ##########
# intended behaviour:
########## TEST DESCRIPTION ##########


echo -n "Current system load: "
cut -d ' ' -f 1 /proc/loadavg

run_previewgen() {
    ITERATIONS=$1
    NICELEVEL=$2
    echo "******************** Running with 'nice -n $NICELEVEL'..."
    OUT="timings/nice_$NICELEVEL.log"
    date >> $OUT
    echo "Timings stored in $OUT"
    for i in $(seq 1 $ITERATIONS) ; do
        echo -n "$i "
        { time nice -n $NICELEVEL $EXP_SCRIPT > /dev/null ; } 2>> $OUT
    done
    echo
}

run_previewgen 10 -10
run_previewgen 10 0

msg_finished

