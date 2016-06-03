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

../vendor/bin/phpdoc -d ../inc/ \
                     -t ${dest} \
                     --ignore "/extern/*" \
                     --visibility=${levels} \
                     --template="responsive-twig"

# Templates: "clean", "responsive-twig"
