#!/bin/bash
#
# Variable definitions to be included in various test scripts.

OMERO_USER="demo01"
OMERO_USER_WRONG="non_existing_user"
OMERO_PW="Dem0o1"
OMERO_PW_WRONG="ThisIsHopefullyNotACorrectPasswordEver"

CONNECTOR_SCRIPT="../../bin/ome_hrm.py"
CONNECTOR_CALL="$CONNECTOR_SCRIPT --user $OMERO_USER --password $OMERO_PW"
CONNECTOR_CALL_USER_WRONG="$CONNECTOR_SCRIPT --user $OMERO_USER_WRONG --password $OMERO_PW"
CONNECTOR_CALL_PW_WRONG="$CONNECTOR_SCRIPT --user $OMERO_USER --password $OMERO_PW_WRONG"
