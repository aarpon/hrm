#!/bin/bash
#
# Package HRM for release in target folder, without touching current environment.
#
# A clean checkout from master is performed.

#
# Input arguments
#
if [ "$#" -ne 2 ]; then
    echo ""
    echo "    Usage: "$0" package_dir archive_name"
    echo ""
    echo "        package_dir : directory where a release version of the code is prepared (e.g. /tmp/release)"
    echo "        archive_name: full file name of the ZIP file to publish (e.g. /tmp/hrm_3.4.0.zip)"
    echo ""
    echo "    Example: "$0" /tmp/hrm /tmp/hrm_3.4.0.zip"
    echo ""
    exit
fi

# Get package directory and archive name.
# To standardize the release (and avoid issues), we always append '/hrm'
# to the suggested package directory.
PARENT_PACKAGE_DIR="$1"
PACKAGE_DIR="${PARENT_PACKAGE_DIR}"/hrm
ARCHIVE_NAME="$2"

#
# Get project directory
#
PROJECT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )/../

###############################################################################
#
# Checkout master into package directory
#
###############################################################################

rm -rf ${PACKAGE_DIR}
git clone -b master --single-branch https://github.com/aarpon/hrm.git ${PACKAGE_DIR}

###############################################################################
#
# Run composer
#
###############################################################################

# Update composer itself
${PACKAGE_DIR}/composer.phar self-update

# Install all third-party dependencies
${PACKAGE_DIR}/composer.phar install --no-dev --working-dir=${PACKAGE_DIR}

# Make sure to add our source to the autoloader path
${PACKAGE_DIR}/composer.phar dump-autoload --optimize --working-dir=${PACKAGE_DIR}

###############################################################################
#
# Package
#
###############################################################################

# Remove all git folders and files
find ${PACKAGE_DIR} -name ".git*" -print0 | xargs -0 rm -rf

# zip it
rm -f ${ARCHIVE_NAME}
cd "${PARENT_PACKAGE_DIR}"
zip -r ${ARCHIVE_NAME} hrm

echo "All done! Your packaged HRM code is in ${ARCHIVE_NAME}."
