#!/usr/bin/env python

"""Simple test script for the HRM class.

Run it from the directory that contains 'HRM.py' after setting your
PYTHONPATH accordingly:

export PYTHONPATH=$PYTHONPATH:./
"""

try:
    import HRM
except ImportError:
    raise SystemExit(__doc__)

jobs_ok = [
    'spool/examples/deconvolution_job.cfg'
]

print('Testing correct job description files:')
for jobfile in jobs_ok:
    job = HRM.JobDescription(jobfile, 'file')
    print(" - Parsing worked without errors on '%s'." % jobfile)

jobs_broken = [
    'broken_sec_deconvolution.cfg',
    'broken_sec_inputfiles.cfg',
    'broken_sec_jobfile.cfg'
]

print('\nTesting incorrect job description files:')
for jobfile in jobs_broken:
    try:
        job = HRM.JobDescription(jobfile, 'file')
    except ValueError:
        print(" - Got the excpected ValueError from '%s'." % jobfile)
