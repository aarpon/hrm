#!/usr/bin/env python

"""Simple tests for the jobfile parsing of the HRM class.

Run it from this directory after setting your PYTHONPATH accordingly:

export PYTHONPATH=$PYTHONPATH:../../lib/python/
python test-005__jobfile-parser.py
"""

try:
    import HRM
except ImportError:
    raise SystemExit(__doc__)

# the reload statement is here so we can use the script in subsequent calls
# during an interactive single IPython session:
reload(HRM)

jobs_ok = [
    '../jobfiles/sandbox/deconvolution_job.cfg'
]

print 'Testing correct job description files:'
for jobfile in jobs_ok:
    job = HRM.JobDescription(jobfile, 'file')
    print " - Parsing worked without errors on '%s'." % jobfile

jobs_broken = [
    '../jobfiles/testing/broken_sec_deconvolution.cfg',
    '../jobfiles/testing/broken_sec_inputfiles.cfg',
    '../jobfiles/testing/broken_sec_jobfile.cfg'
]

print '\nTesting invalid job description files:'
for jobfile in jobs_broken:
    try:
        job = HRM.JobDescription(jobfile, 'file')
    except ValueError as err:
        print(" - Got the excpected ValueError from '%s':\n   %s" %
              (jobfile, err))
