#!/bin/sh

RESULTDIR="results"

set -e
rm -rf $RESULTDIR
mkdir -pv $RESULTDIR
set +e

for TEST in test__*__*.sh ; do
    echo "Running $TEST..."
    STDOUT="$RESULTDIR/${TEST}_stdout"
    STDERR="$RESULTDIR/${TEST}_stderr"
    EXITVAL="$RESULTDIR/${TEST}_exitval"
    bash $TEST >$STDOUT 2>$STDERR
    echo $? > $EXITVAL
done
