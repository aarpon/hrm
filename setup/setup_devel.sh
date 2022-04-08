#!/bin/bash
#
# Set up environment for HRM development.
#
# Requirements:
#
#  - php-xdebug
#  - php-xml
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

echo "Set up HRM for development... $PROJECT_DIR"

# Update composer itself
${PROJECT_DIR}/composer.phar self-update

# Make sure all third-party dependencies exist and are up-to-date
if [ ! -d "${PROJECT_DIR}/vendor" ]; then
    # Install
    ${PROJECT_DIR}/composer.phar install --working-dir=${PROJECT_DIR}
else
    # Update
    ${PROJECT_DIR}/composer.phar --working-dir=${PROJECT_DIR} update
fi

# Make sure to add our source to the autoloader path
${PROJECT_DIR}/composer.phar dump-autoload --optimize --working-dir=${PROJECT_DIR}

# Set the default coding standard for phpcs
${PROJECT_DIR}/vendor/bin/phpcs --config-set default_standard PSR12

