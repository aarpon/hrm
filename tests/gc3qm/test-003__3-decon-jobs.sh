#!/bin/bash

# exit on any error:
set -e

# remember path prefix as called, source functions
PFX=$(dirname $0)
source "$PFX/functions.inc.sh"

SHORT=$(parse_shortname)

########## TEST DESCRIPTION ##########
# intended behaviour:
# 1) start the QM
# 2) switch to pause mode
# 3) place three deconvolution jobs in the queue
# 4) switch to run mode
# 5) shutdown QM when queue is empty, latest after 5 min
########## TEST DESCRIPTION ##########


startup_qm

qm_request pause
sleep 1

# we are in the HRM base dir now, so use the full path for job files:
for jobfile in tests/gc3qm/inputs/$SHORT/*.cfg ; do
    cp -v $jobfile "$QM_SPOOL/spool/new"
    sleep .2
done

qm_request run
sleep 1

shutdown_qm_on_empty_queue 300

msg_finished

