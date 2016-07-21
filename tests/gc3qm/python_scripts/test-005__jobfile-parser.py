#!/usr/bin/env python

"""Simple tests for the jobfile parsing of the HRM class.

Run it from this directory after setting your PYTHONPATH accordingly:

export PYTHONPATH=$PYTHONPATH:../../lib/python/
python test-005__jobfile-parser.py
"""

import glob
import pprint

try:
    import HRM
except ImportError:
    raise SystemExit(__doc__)

# the reload statement is here so we can use the script in subsequent calls
# during an interactive single IPython session:
reload(HRM)


print '\n>>>>>> Testing CORRECT job description files:\n'
jobfile_list = glob.glob('jobfiles/*.cfg')
jobfile_list.sort()
for jobfile in jobfile_list:
    print "----------------- parsing %s -----------------" % jobfile
    job = HRM.JobDescription(jobfile, 'file')
    print " - Parsing worked without errors on '%s'." % jobfile
    pprint.pprint(job)


print '\n>>>>>> Testing INVALID job description files:'
jobfile_list = glob.glob('jobfiles/invalid/*.cfg')
jobfile_list.sort()
for jobfile in jobfile_list:
    print "----------------- parsing %s -----------------" % jobfile
    try:
        job = HRM.JobDescription(jobfile, 'file')
    except ValueError as err:
        print(" - Got the excpected ValueError from '%s':\n   %s\n" %
              (jobfile, err))
