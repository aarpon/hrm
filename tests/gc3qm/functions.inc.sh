#!/bin/bash
#
# function definitions to be included in various test scripts.

QM_PY="bin/hrm_queuemanager.py"
# the "-u" flag requests Python to run with unbuffered stdin/stdout, which is
# required for testing to ensure the messages are always printed in the very
# order in which the program(s) were sending them:
QM_EXEC="python -u $QM_PY"
QM_SPOOL="run"  # TODO: read this from hrm.conf once it's there!
QM_OPTS="--spooldir $QM_SPOOL --config config/samples/gc3pie_localhost.conf -v"


clean_all_spooldirs() {
    set +e
    rm -vf /data/gc3_resourcedir/shellcmd.d/*
    rm -vf "../../$QM_SPOOL/spool/cur/"*
    rm -vf "../../$QM_SPOOL/queue/requests/"*
    set -e
}


check_spooldirs_clean() {
    # test if all relevant spooling directories are empty, EXIT otherwise!
    for DIR in new cur ; do
        if ! spooldir_is_empty "$DIR" ; then
            echo "ERROR: unclean spooling directory '$DIR' found! Stopping."
            exit 1
        fi
    done
}


spooldir_is_empty() {
    # check if a given spool directory contains files
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


hucore_is_running() {
    test $(pgrep --count --full "hucore.bin") -gt 0
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
    # test if the QM process is alive by sending a "refresh" request:
    qm_request refresh
    # give the QM some time to process the "refresh" request, so the followup
    # requests won't get mixed with this one:
    sleep 1
    echo "QM process started."
}


submit_jobs() {
    # copy jobfiles with a given prefix into the spool/new directory to submit
    # the to a running queue manager
    if [ -z "$1" ] ; then
        echo "ERROR: no jobfile prefix given for submission!"
        exit 4
    fi
    # we are expected to be in the HRM base dir, so use the full path:
    for jobfile in tests/gc3qm/inputs/$SHORT/${1}*.cfg ; do
        cp -v $jobfile "$QM_SPOOL/spool/new"
        sleep .1
    done

}


wait_for_hucore_to_finish() {
    # try to terminate any still-running hucore processes
    if hucore_is_running ; then
        if [ -n "$1" ] ; then
            echo "WARNING: Found running HuCore processes, waiting..."
            sleep $1
        fi
        if hucore_is_running ; then
            echo "==============================================================="
            echo "WARNING: Found running HuCore processes, trying to kill them..."
            echo "==============================================================="
            killall hucore.bin
        fi
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
            wait_for_hucore_to_finish
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
    wait_for_hucore_to_finish 2
}


queue_is_empty() {
    # Test if the queue is empty, EXIT if the queue file doesn't exist!
    QFILE="$QM_SPOOL/queue/status/hucore.json"
    if [ -n "$1" ] ; then
        QFILE="$QM_SPOOL/queue/status/$1.json"
    fi
    # the queue file *HAS TO* exist, otherwise we terminate with an error:
    if ! [ -r "$QFILE" ] ; then
        echo "ERROR: queue file '$QFILE' doesn't exist!"
        exit 100
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
        echo "==============================================================="
        echo "ERROR: Queue still not empty after $1 secondes!"
        echo "==============================================================="
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

strip_runtime_strings() {
    # strips away various hashes that are runtime-specific, to make the result
    # better comparable among subsequent individual runs
    sed -s 's/[0-9a-f]\{40\}/UID_STRIPPED/g' |
    sed -s 's/App@[0-9a-f]\{12\}/App@APPID_STRIPPED/g' |
    sed -s 's/[0-9]\{10\}\.[0-9]\{4,6\}/TIMESTAMP_STRIPPED/g'
}
