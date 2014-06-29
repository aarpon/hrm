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

# the reload statement is here so we can us the script in subsequent calls
# during a single IPython session:
reload(HRM)

job1 = HRM.JobDescription('spool/new/job1.cfg', 'file')
job2 = HRM.JobDescription('spool/new/job1.cfg', 'file')
job3 = HRM.JobDescription('spool/new/job1.cfg', 'file')

job1['uid'] = 'aaa'
job2['uid'] = 'bbb'
job3['uid'] = 'ccc'

jq = HRM.JobQueue()
jq.append(job1)
jq.append(job2)
jq.append(job3)

jq.pop()
jq.pop()
jq.pop()
jq.pop()

jq.append(job1)
jq.append(job2)
jq.append(job3)

jq.remove('aaa')
jq.remove('bbb')
jq.remove('ccc')
jq.remove('aaa')

