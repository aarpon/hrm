#!/usr/bin/env bash
# Usage: build_docs [--public]
#
#    --public: only document public methods and attributes

if [ "$1" = "--public" ] ; then
    levels="public"
    echo "Building public API..."
    dest="./api/public"
else
    levels="public,protected,private"
    echo "Building private API..."
    dest="./api/private"
fi

./apigen.phar generate \
    --source ../inc --destination ${dest} \
    --access-levels="${levels}" \
    --todo \
    --exclude="extern" \
    --title="Huygens Remote Manager" \
    --no-source-code \
    --tree \
    --template-theme bootstrap
