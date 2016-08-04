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
# 3) place the "decon_job" from the inputs directory in the queue
# 4) place the "remove_job" from the inputs directory in the queue
# 5) switch to run mode, this is expected to remove the job from the queue
# 6) shutdown QM when queue is empty (should be IMMEDIATE!), latest after 5 SECONDS
########## TEST DESCRIPTION ##########


clean_all_spooldirs

startup_qm

qm_request pause
sleep 1

submit_jobs "decon_job_"
sleep .5

submit_jobs "remove_job_"
sleep .5

qm_request run

shutdown_qm_on_empty_queue 5

msg_finished

