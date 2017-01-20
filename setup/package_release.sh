#!/bin/bash
#
# Package HRM for release in target folder, without touching current environment.
#
# Requirements:
#
#  - php-xdebug
#  - php-xml
#

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

#
# Copy the hrm project directory to the package directory
#
rm -rf ${PACKAGE_DIR}
cp -R ${PROJECT_DIR} ${PACKAGE_DIR}

###############################################################################
#
# Run composer
#
###############################################################################

echo "Set up HRM for release in ${PACKAGE_DIR}"

# Update composer itself
${PACKAGE_DIR}/composer.phar self-update

# Make sure all third-party dependencies exist and are up-to-date
if [ ! -d "${PACKAGE_DIR}/vendor" ]; then
    # Install
    ${PACKAGE_DIR}/composer.phar install --no-dev --working-dir=${PACKAGE_DIR}
else
    # Update
    ${PACKAGE_DIR}/composer.phar update --no-dev --working-dir=${PACKAGE_DIR}
fi

# Make sure to add our source to the autoloader path
${PACKAGE_DIR}/composer.phar dump-autoload --optimize --working-dir=${PACKAGE_DIR}

###############################################################################
#
# Package
#
###############################################################################

# Remove all git folders and files and idea
find ${PACKAGE_DIR} -name ".git*" -print0 | xargs -0 rm -rf
find ${PACKAGE_DIR} -name ".idea" -print0 | xargs -0 rm -rf

# zip it
rm -f ${ARCHIVE_NAME}
cd "${PARENT_PACKAGE_DIR}"
zip -r ${ARCHIVE_NAME} hrm

echo "All done! Your packaged HRM code is in ${ARCHIVE_NAME}."
