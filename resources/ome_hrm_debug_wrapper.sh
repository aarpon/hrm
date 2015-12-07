#!/bin/sh

BASEDIR=$(dirname $0)

# export PYTHONPATH="/opt/OMERO/python-extlibs":$PYTHONPATH
{
    echo $BASEDIR
    echo "ome_hrm wrapper"
    echo '$PYTHONPATH: '$PYTHONPATH
    $BASEDIR/ome_hrm.py.real $@
} >> /tmp/foooo 2>&1
