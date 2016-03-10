import sys

print "sys.version:", sys.version
print "sys.executable:", sys.executable

import argparse
print "argparse:", argparse.__file__, "version:", argparse.__version__

import os
print "os:", os.__file__

import json
print "json:", json.__file__, "version:", json.__version__

import re
print "re:", re.__file__, "version:", re.__version__

OMERO_LIB='/opt/OMERO/OMERO.server-5.1.2-ice34-b45/lib/python'
sys.path.insert(0, OMERO_LIB)
print "sys.path: ["
for path in sys.path:
    print "    '%s'," % path
print "]"

try:
    import omero
    print "OMERO Python bindings:", omero.__file__
    from omero.gateway import BlitzGateway
    print "Successfully loaded OMERO's BlitzGateway."
except ImportError as err:
    print "ERROR importing the OMERO Python bindings! Message:", err
    sys.exit(1)
