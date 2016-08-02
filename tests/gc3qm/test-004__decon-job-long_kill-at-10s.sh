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
# 3) place the jobs from the inputs directory in the queue
# 4) switch to run mode
# 5) shutdown QM when queue is empty, latest after 5 SECONDS
########## TEST DESCRIPTION ##########


clean_all_spooldirs

startup_qm

qm_request pause
sleep 1

submit_jobs "decon_job_"
sleep .5

qm_request run
sleep 1

shutdown_qm_on_empty_queue 5

msg_finished

