#!/bin/bash
#
# function definitions to be included in various test scripts.

QM_PY="bin/hrm_queuemanager.py"
QM_EXEC="python $QM_PY"
QM_SPOOL="run"
QM_OPTS="--spooldir $QM_SPOOL --config config/samples/gc3pie_localhost.conf -v"


check_spooldirs_clean() {
    for DIR in new cur ; do
        if ! spooldir_is_empty "$DIR" ; then
            echo "ERROR: unclean spooling directory '$DIR' found! Stopping."
            exit 1
        fi
    done
}


spooldir_is_empty() {
    if [ -z "$1" ] ; then
        echo "ERROR No spooling dir specified to check!"
        exit 255
    fi
    DIR="../../$QM_SPOOL/spool/$1/"
    COUNT=$(ls "$DIR" | wc -l)
    if [ $COUNT -eq 0 ] ; then
        # echo "No jobs in '$1' spooling directory!"
        return 0
    else
        echo "WARNING: found $COUNT jobfiles in '$1': $DIR"
        return 1
    fi
}


qm_is_running() {
    test $(pgrep --count --full "$QM_EXEC") -gt 0
}


qm_request() {
    # send a status change request to the queue manager, making sure the actual
    # process is still alive (EXIT otherwise!)
    echo "Requesting QM status change to: $1"
    if ! qm_is_running ; then
        echo "ERROR: QM is not running! Stopping here."
        exit 3
    fi
    touch "$QM_SPOOL/queue/requests/$1"
}


startup_qm() {
    # Start a fresh instance of the QM, making sure no other one is running.
    # NOTE: this changes the working directory to the base HRM dir!
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
    echo $QM_EXEC $QM_OPTS
    $QM_EXEC $QM_OPTS &
    # remember the PID of the background process:
    QM_PID=$!
    # give the QM some time to start up
    sleep 1
    # test if the QM process is alive by sending a "run" request:
    qm_request run
    echo "QM process started."
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
        pkill --signal HUP --full "$QM_EXEC"
        sleep 1
    fi
    if qm_is_running ; then
        echo "ERROR: QM doesn't react to HUP, giving up!!"
    fi
}


queue_is_empty() {
    QFILE="$QM_SPOOL/queue/status/hucore.json"
    if [ -n "$1" ] ; then
        QFILE="$QM_SPOOL/queue/status/$1.json"
    fi
    # cat "$QM_SPOOL/queue/status/hucore.json"
    QUEUED=$(grep '"status":' "$QFILE" | wc -l)
    if [ "$QUEUED" -eq 0 ] ; then
        # echo "Queue is empty!"
        return 0
    else
        # echo "--> $QUEUED jobs currently queued"
        return 1
    fi
}


shutdown_qm_on_empty_queue() {
    if ! qm_is_running ; then
        echo "WARNING: QM is not running!"
        return
    fi
    for counter in $(seq 1 $1) ; do
        if queue_is_empty ; then
            echo "Queue is empty, trying to shut down the QM!"
            break
        fi
        sleep 1
    done
    if ! queue_is_empty ; then
        echo "=============================================================="
        echo "ERROR: Queue still not empty after $1 secondes!"
        echo "=============================================================="
    fi
    qm_request shutdown
    wait_for_qm_to_finish 5
}

msg_finished() {
    echo "************************* TEST FINISHED! *************************"
}


parse_shortname() {
    SHORT=$(basename $0 | sed 's,__.*,,')
    echo $SHORT
}
