#!/bin/bash
#
# Set up environment for HRM release (in place).
#

#
# Set project directory
#
PROJECT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )/../

###############################################################################
#
# Composer
#
###############################################################################

echo "Set up HRM for release **IN PLACE**... $PROJECT_DIR"

# Update composer itself
${PROJECT_DIR}/composer.phar self-update

# Make sure all third-party dependencies exist and are up-to-date
if [ ! -d "${PROJECT_DIR}/vendor" ]; then
    # Install
    ${PROJECT_DIR}/composer.phar install --no-dev --working-dir=${PROJECT_DIR}
else
    # Update
    ${PROJECT_DIR}/composer.phar update --no-dev --working-dir=${PROJECT_DIR}
fi

# Make sure to add our source to the autoloader path
${PROJECT_DIR}/composer.phar dump-autoload --optimize --working-dir=${PROJECT_DIR}

###############################################################################
#
# Apply necessary patches
#
###############################################################################

echo "Patching... "
patch ${PROJECT_DIR}/vendor/adldap2/adldap2/lib/adLDAP/classes/adLDAPUsers.php ${PROJECT_DIR}/setup/adldap2.patch
