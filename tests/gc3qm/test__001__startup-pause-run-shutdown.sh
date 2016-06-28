#!/bin/bash

# exit on any error:
set -e

# remember path prefix as called, source functions
PFX=$(dirname $0)
source "$PFX/functions.inc.sh"

startup_single_qm_instance

qm_request pause
sleep 1

qm_request run
sleep 1

# cp tests/jobfiles/sandbox/user01_decon_job_it-3.cfg run/spool/new/
# sleep .2
# cp tests/jobfiles/sandbox/user01_decon_job_it-3.cfg run/spool/new/
# sleep .2
# cp tests/jobfiles/sandbox/user01_decon_job_it-3.cfg run/spool/new/
# touch run/queue/requests/run

qm_request shutdown

wait_for_qm_to_finish 1

echo "TEST FINISHED!"
