#!/bin/bash

# exit on any error:
set -e

# remember path prefix as called, source functions
PFX=$(dirname $0)
source "$PFX/functions.inc.sh"

SHORT=$(parse_shortname)

########## TEST DESCRIPTION ##########
# intended behaviour:
# 1) start the QM (in 'run' mode)
# 3) place the "decon_job" from the inputs directory in the queue
# 4) request queue status
# 5) place the "remove_job" from the inputs directory in the queue
# 6) request queue status
# 7) shutdown QM when queue is empty (should be IMMEDIATE!), latest after 5 SECONDS
########## TEST DESCRIPTION ##########

SEP="#######################################"
SEP="$SEP$SEP"

clean_all_spooldirs

startup_qm

submit_jobs "decon_job_"

qm_request refresh
sleep 1

echo
echo $SEP
echo $SEP >&2
echo

submit_jobs "remove_job_"
sleep 1

echo
echo $SEP
echo $SEP >&2
echo

qm_request refresh

echo
echo $SEP
echo $SEP >&2
echo

sleep .6

shutdown_qm_on_empty_queue 5

msg_finished

