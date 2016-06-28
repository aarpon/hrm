#!/bin/bash

# exit on any error:
set -e

# remember path prefix as called, source functions
PFX=$(dirname $0)
source "$PFX/functions.inc.sh"


########## TEST DESCRIPTION ##########
# intended behaviour:
# 1) start the QM
# 2) switch to pause mode, then back to run
# 3) shutdown the QM
########## TEST DESCRIPTION ##########


startup_qm

qm_request pause
sleep 1

qm_request run
sleep 1

qm_request shutdown

wait_for_qm_to_finish 1

msg_finished
