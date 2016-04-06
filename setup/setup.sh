#!/bin/bash

#
# Set project directory
#
PROJECT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )/../

###############################################################################
#
# Composer
#
###############################################################################

# Update composer itself
${PROJECT_DIR}/composer.phar self-update

# Make sure all third-party dependencies exist and are up-to-date
if [ ! -d "${PROJECT_DIR}/vendor" ]; then
    # Install
    ${PROJECT_DIR}/composer.phar install
else
    # Update
    ${PROJECT_DIR}/composer.phar update
fi

