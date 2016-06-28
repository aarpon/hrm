#!/bin/bash
#
# function definitions to be included in various test scripts.

QM_PY="bin/hrm_queuemanager.py"
QM_RUN="python $QM_PY"
QM_SPOOL="run"
QM_OPTS="--spooldir $QM_SPOOL --config config/samples/gc3pie_localhost.conf -v"


qm_is_running() {
    test $(pgrep --count --full "$QM_RUN") -gt 0
}


qm_request() {
    echo "Requesting QM status change to: $1"
    touch "$QM_SPOOL/queue/requests/$1"
}


startup_qm() {
    # Start a fresh instance of the QM, making sure no other one is running.
    if ! [ -f "$PFX/../../$QM_PY" ] ; then
        echo "ERROR: can't find queue manager executable!"
        exit 2
    fi
    cd "$PFX/../.."
    if qm_is_running ; then
        echo
        echo "****************************************************************"
        echo " ERROR: Queue Manager seems to be running already!"
        echo "  --> NOT starting another one to prevent unexpected behaviour."
        echo "****************************************************************"
        echo
        exit 1
    fi
    echo "**** Starting Queue Manager..."
    echo $QM_RUN $QM_OPTS
    $QM_RUN $QM_OPTS &
    # remember the PID of the background process:
    QM_PID=$!
    # test if the QM process is alive:
    sleep .2
    if qm_is_running ; then
        echo "QM process started."
    else
        echo "ERROR: QM startup FAILED!"
        exit 3
    fi
}


wait_for_qm_to_finish() {
    # Wait a given number of seconds for the QM process to terminate,
    # otherwise try to shut it down (gracefully, using a shutdown request), or
    # try to kill it as a last resort.
    if qm_is_running ; then
        echo "QM is running..."
    fi
    for counter in $(seq 1 $1) ; do
        sleep 1
        if ! qm_is_running ; then
            echo "QM process terminated."
            return
        fi
    done
    if qm_is_running ; then
        echo "WARNING: QM is STILL running after $1 seconds!"
        echo "Trying to shut it down..."
        qm_request shutdown
        sleep 1
    fi
    if qm_is_running ; then
        echo "WARNING: QM doesn't listen to our shutdown request!"
        echo "Trying to kill it..."
        pkill --signal HUP --full "$QM_RUN"
        sleep 1
    fi
    if qm_is_running ; then
        echo "ERROR: QM doesn't react to HUP, giving up!!"
    fi
}


msg_finished() {
    echo "************************* TEST FINISHED! *************************"
}
