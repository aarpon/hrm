#!/usr/bin/env python

"""Simple test script for the HRM Queue Manager class.

Run it from this directory after setting your PYTHONPATH accordingly:

export PYTHONPATH=$PYTHONPATH:../../lib/python/
python test_HRM_JobQueue.py
"""

try:
    import HRM
except ImportError:
    raise SystemExit(__doc__)

# the reload statement is here so we can us the script in subsequent calls
# during a single IPython session:
reload(HRM)

jobs = list(xrange(7))

jobfile = '../jobfiles/sandbox/deconvolution_job.cfg'
for i in xrange(7):
    jobs[i] = HRM.JobDescription(jobfile, 'file')

jobs[0]['uid'] = 'aaa'
jobs[1]['uid'] = 'bbb'
jobs[2]['uid'] = 'ccc'

jobs[3]['uid'] = 'ddd'
jobs[3]['user'] = 'foo'
jobs[4]['uid'] = 'eee'
jobs[4]['user'] = 'foo'
jobs[5]['uid'] = 'fff'
jobs[5]['user'] = 'foo'
jobs[6]['uid'] = 'ggg'
jobs[6]['user'] = 'foo'

jq = HRM.JobQueue()

print("\n******** adding jobs to queue: ********")
for job in jobs:
    jq.append(job)

print("\n******** retrieving jobs from queue: ********")
for job in jobs:
    jq.pop()


print("\n******** adding jobs to queue: ********")
for job in jobs:
    jq.append(job)

print("\n******** removing jobs from queue: ********")
jq.remove('aaa')
jq.remove('bbb')
jq.pop()
jq.remove('ggg')
jq.pop()
jq.remove('ccc')
jq.remove('fff')
jq.remove('aaa')

