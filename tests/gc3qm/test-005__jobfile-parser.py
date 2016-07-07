#!/usr/bin/env python

"""Simple tests for the jobfile parsing of the HRM class.

Run it from this directory after setting your PYTHONPATH accordingly:

export PYTHONPATH=$PYTHONPATH:../../lib/python/
python test-005__jobfile-parser.py
"""

import glob

try:
    import HRM
except ImportError:
    raise SystemExit(__doc__)

# the reload statement is here so we can use the script in subsequent calls
# during an interactive single IPython session:
reload(HRM)


print 'Testing correct job description files:'
for jobfile in glob.glob('jobfiles/*.cfg'):
    job = HRM.JobDescription(jobfile, 'file')
    print " - Parsing worked without errors on '%s'." % jobfile


print '\nTesting invalid job description files:'
for jobfile in glob.glob('jobfiles/invalid/*.cfg'):
    try:
        job = HRM.JobDescription(jobfile, 'file')
    except ValueError as err:
        print(" - Got the excpected ValueError from '%s':\n   %s" %
              (jobfile, err))
