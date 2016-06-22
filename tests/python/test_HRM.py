#!/usr/bin/env python

"""Simple test script for the HRM class.

Run it from this directory after setting your PYTHONPATH accordingly:

export PYTHONPATH=$PYTHONPATH:../../lib/python/
python test_HRM.py
"""

try:
    import HRM
except ImportError:
    raise SystemExit(__doc__)

# the reload statement is here so we can us the script in subsequent calls
# during a single IPython session:
reload(HRM)

jobs_ok = [
    '../jobfiles/sandbox/deconvolution_job.cfg'
]

print('Testing correct job description files:')
for jobfile in jobs_ok:
    job = HRM.JobDescription(jobfile, 'file')
    print(" - Parsing worked without errors on '%s'." % jobfile)

jobs_broken = [
    '../jobfiles/testing/broken_sec_deconvolution.cfg',
    '../jobfiles/testing/broken_sec_inputfiles.cfg',
    '../jobfiles/testing/broken_sec_jobfile.cfg'
]

print('\nTesting incorrect job description files:')
for jobfile in jobs_broken:
    try:
        job = HRM.JobDescription(jobfile, 'file')
    except ValueError:
        print(" - Got the excpected ValueError from '%s'." % jobfile)
