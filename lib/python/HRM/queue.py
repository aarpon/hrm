#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Queue class module for the HRM.

Classes
-------

JobQueue()
    Job handling and scheduling.
"""

import itertools
import json
from collections import deque
import gc3libs

from .logger import *


class JobQueue(object):

    """Class to store a list of jobs that need to be processed.

    An instance of this class can be used to keep track of lists of jobs of
    different categories (e.g. individual users). The instance will contain a
    scheduler so that it is possible for the caller to simply request the next
    job from this queue without having to care about priorities or anything
    else.
    """

    def __init__(self):
        """Initialize an empty job queue.

        Instance Variables
        ------------------
        statusfile : str (default=None)
            file name used to write the JSON formatted queue status to
        cats : deque
            categories (users), used by the scheduler
        jobs : dict(JobDescription)
            holding job descriptions (key: UID)
        processing : list
            UID's of jobs being processed currently
        queue : dict(deque)
            queues of each category (user)
        deletion_list : list
            UID's of jobs to be deleted from the queue (NOTE: this list may
            contain UID's from other queues as well!)
        """
        self.statusfile = None
        self.cats = deque('')
        self.jobs = dict()
        self.processing = list()
        self.queue = dict()
        self.deletion_list = list()

    def __len__(self):
        """Get the total number of jobs in all queues (incl. processing)."""
        jobsproc = self.num_jobs_processing()
        jobstotal = self.num_jobs_queued() + jobsproc
        logd("len(JobQueue) = %s (%s processing)", jobstotal, jobsproc)
        return jobstotal

    def num_jobs_queued(self):
        """Get the number of queued jobs (waiting for retrieval)."""
        numjobs = 0
        for queue in self.queue.values():
            numjobs += len(queue)
        logd("num_jobs_queued = %s", numjobs)
        return numjobs

    def num_jobs_processing(self):
        """Get the number of currently processing jobs."""
        numjobs = len(self.processing)
        logd("num_jobs_processing = %s", numjobs)
        return numjobs

    def set_statusfile(self, statusfile):
        """Set the file used to place the (JSON formatted) queue status in.

        Parameters
        ----------
        statusfile : str
        """
        logi("Setting job queue status report file: %s", statusfile)
        self.statusfile = statusfile

    def append(self, job):
        """Add a new job to the queue.
        Parameters
        ----------
        job : JobDescription
            The job to be added to the queue.
        """
        cat = job.get_category()
        uid = job['uid']
        if uid in self.jobs:
            raise ValueError("Job with uid '%s' already in this queue!" % uid)
        logi("Enqueueing job '%s' into category '%s'.", uid, cat)
        self.jobs[uid] = job  # store the job in the global dict
        if cat not in self.cats:
            logi("Adding a new queue for '%s' to the JobQueue.", cat)
            self.cats.append(cat)
            self.queue[cat] = deque()
            logd("Current queue categories: %s", self.cats)
        # else:
        #     # in case there are already jobs of this category, we don't touch
        #     # the scheduler / priority queue:
        #     logd("JobQueue already contains a queue for '%s'.", cat)
        self.queue[cat].append(uid)
        self.set_jobstatus(job, 'queued')
        logi("Job (type '%s') added. New queue: %s", job['type'], self.queue)
        self.queue_details_hr()

    def _is_queue_empty(self, cat):
        """Clean up if a queue of a given category is empty.

        Returns
        -------
        status : bool
            True if the queue was empty and removed, False otherwise.
        """
        if len(self.queue[cat]) == 0:
            logd("Queue for category '%s' now empty, removing it.", cat)
            self.cats.remove(cat)  # remove it from the categories list
            del self.queue[cat]    # delete the category from the queue dict
            return True
        else:
            return False

    def next_job(self):
        """Return the next job description for processing.

        Picks the next that should be processed from that queue that has the
        topmost position in the categories queue. After selecting the job, the
        categories queue is shifted one to the left, meaning that the category
        of the just picked job is then at the last position in the categories
        queue.
        This implements a very simple round-robin (token based) scheduler that
        is going one-by-one through the existing categories.

        Returns
        -------
        job : JobDescription
        """
        if len(self.cats) == 0:
            return None
        cat = self.cats[0]
        jobid = self.queue[cat].popleft()
        # put it into the list of currently processing jobs:
        self.processing.append(jobid)
        logi("Retrieving next job: category '%s', uid '%s'.", cat, jobid)
        if not self._is_queue_empty(cat):
            # push the current category to the last position in the queue:
            self.cats.rotate(-1)
        logd("Current queue categories: %s", self.cats)
        logd("Current contents of all queues: %s", self.queue)
        return self.jobs[jobid]

    def remove(self, uid, update_status=True):
        """Remove a job with a given UID from the queue.

        Take a job UID and remove the job from the list of currently processing
        jobs or its category's queue, cleaning up the queue if necessary.

        Parameters
        ----------
        uid : str
            UID of job to remove
        update_status : bool (optional, default=True)
            update the queue status file after a job has been successfully
            removed - set to 'False' to avoid unnecessary status updates e.g.
            in case of bulk deletion requests

        Returns
        -------
        job : JobDescription
            The JobDescription dict of the job that was removed (on success).
        """
        logd("Trying to remove job with uid '%s'.", uid)
        if uid not in self.jobs:
            logw("No job with uid '%s' was found!", uid)
            return None
        job = self.jobs[uid]   # remember the job for returning it later
        cat = job.get_category()
        logi("Status of job to be removed: %s", job['status'])
        del self.jobs[uid]     # remove the job from the jobs dict
        if cat in self.queue and uid in self.queue[cat]:
            logd("Removing job '%s' from queue '%s'.", uid, cat)
            self.queue[cat].remove(uid)
            self._is_queue_empty(cat)
        elif uid in self.processing:
            logd("Removing job '%s' from currently processing jobs.", uid)
            self.processing.remove(uid)
        else:
            logw("Can't find job '%s' in any of our queues!", uid)
            return None
        # logd("Current jobs: %s", self.jobs)
        # logd("Current queue categories: %s", self.cats)
        # logd("Current contents of all queues: %s", self.queue)
        if update_status:
            logd(self.queue_details_json())
        return job

    def process_deletion_list(self):
        """Remove jobs from this queue that are on the deletion list."""
        for uid in self.deletion_list:
            logi("Job %s was requested for deletion", uid)
            removed = self.remove(uid, update_status=False)
            if removed is None:
                # this is to be expected, so we only print a log message if we
                # are in debug mode...
                logd("No job removed, invalid uid or other queue's job.")
            else:
                logi("Job successfully removed from the queue.")
                self.deletion_list.remove(uid)
        # updating the queue status file is only done now:
        logd(self.queue_details_json())

    def set_jobstatus(self, job, status):
        """Update the status of a job and trigger related actions."""
        logd("Changing status of job %s to %s", job['uid'], status)
        job['status'] = status
        if status == gc3libs.Run.State.TERMINATED:
            self.remove(job['uid'])
        logd(self.queue_details_json())

    def queue_details_json(self):
        """Generate a JSON representation of the queue details.

        The details are returned in a dict of the following form:
        details = { "jobs" :
            [
                {
                    "username" : "user00",
                    "status"   : "N/A",
                    "queued"   : 1437152020.751692,
                    "file"     : [ "tests/jobfiles/sandbox/faba128.h5" ],
                    "start"    : "N/A",
                    "progress" : "N/A",
                    "pid"      : "N/A",
                    "id"       : "8cd0d80f36dd8f7655bde8679b192f526f9541bb",
                    "jobType"  : "hucore",
                    "server"   : "N/A"
               },
            ]
        }
        """
        # FIXME: the "file" field is a list intentionally, as jobs can easily
        # consist of multiple input files (TIFF series, ICS/IDS, etc.), so
        # don't just use the first file (requires the PHP part to be adapted).
        def format_job(job):
            """Helper function to assemble the job dict."""
            fjob = {
                "id"      : job['uid'],
                "file"    : job['infiles'][0],  # TODO: see above!
                "username": job['user'],
                "jobType" : job['type'],
                "status"  : job['status'],
                "server"  : 'N/A',
                "progress": 'N/A',
                "pid"     : 'N/A',
                "start"   : 'N/A',
                "queued"  : job['timestamp'],
            }
            return fjob
        joblist = self.queue_details()
        formatted = []
        for jobid in self.processing:
            job = self.jobs[jobid]
            formatted.append(format_job(job))
        for job in joblist:
            formatted.append(format_job(job))
        details = {'jobs' : formatted}
        if self.statusfile is not None:
            with open(self.statusfile, 'w') as fout:
                json.dump(details, fout, indent=4)
        return json.dumps(details, indent=4)

    def queue_details_hr(self):
        """Generate a human readable list of the queue details."""
        # FIXME: don't print the queue status, return the text instead!
        #        this produce annoying amounts of output in a production
        #        environment, therefore the generated text should be returned,
        #        so it can be handled by the logging methods (e.g. only printed
        #        when running in debug mode)
        msg = list()
        msg.append("%s queue status %s" % ("=" * 25, "=" * 25))
        msg.append("--- jobs retrieved for processing")
        if not self.processing:
            msg.append("None.")
        for jobid in self.processing:
            job = self.jobs[jobid]
            msg.append("%s (%s): %s - %s [%s]" %
                       (job['user'], job['email'], job['uid'],
                        job['infiles'], job['status']))
        msg.append("%s queue status %s" % ("-" * 25, "-" * 25))
        msg.append("--- jobs queued (not yet retrieved)")
        joblist = self.queue_details()
        if not joblist:
            msg.append("None.")
        for job in joblist:
            msg.append("%s (%s): %s - %s [%s]" %
                       (job['user'], job['email'], job['uid'],
                        job['infiles'], job['status']))
        msg.append("%s queue status %s" % ("=" * 25, "=" * 25))
        for line in msg:
            print line

    def queue_details(self):
        """Generate a list with the current queue details."""
        return [self.jobs[jobid] for jobid in self.joblist()]

    def joblist(self):
        """Generate a list with job ids respecting the current queue order.

        For now this simply interleaves all queues from all users, until we
        have implemented a more sophisticated scheduling. However, as the plan
        is to have a dynamic scheduling mechanism, the order of the jobs in
        the queue will be subject to constant change - and therefore the
        queue details will in the best case give an estimate of which jobs
        will be run next.

        Example
        -------
        Given the following queue status:
            self.queue = {
                'user00': deque(['u00_j0', 'u00_j1', 'u00_j2', 'u00_j3']),
                'user01': deque(['u01_j0', 'u01_j1', 'u01_j2']),
                'user02': deque(['u02_j0', 'u02_j1'])
            }

        will result in a list of job dicts in the following order:
            ['u02_j0', 'u01_j0', 'u00_j0',
             'u02_j1', 'u01_j1', 'u00_j1'
             'u01_j2', 'u00_j2'
             'u00_j3']

        where each of the dicts will be of this format:
            {'ver': '5',
             'infiles': ['tests/jobfiles/sandbox/faba128.h5'],
             'exec': '/usr/local/bin/hucore',
             'timestamp': 1437123471.579627,
             'user': 'user00',
             'template': 'hrm_faba128_iterations-3.hgsb',
             'type': 'hucore',
             'email': 'user00@mail.xy',
             'uid': '2f53d7f50c22285a92c7fcda74994a69f72e1bf1'}

        """
        joblist = []
        # if the queue is empty, we return immediately with an empty list:
        if len(self) == 0:
            logd('Empty queue!')
            return joblist
        # put queues into a list of lists, respecting the current queue order:
        queues = [self.queue[cat] for cat in self.cats]
        # turn into a zipped list of the queues of all users, padding with
        # 'None' to compensate the different queue lengths:
        queues = [x for x in itertools.izip_longest(*queues)]
        # with the example values, this results in the following:
        # [('u02_j0', 'u01_j0', 'u00_j0'),
        #  ('u02_j1', 'u01_j1', 'u00_j1'),
        #  (None,     'u01_j2', 'u00_j2'),
        #  (None,     None,     'u00_j3')]

        # now flatten the tuple-list and fill with the job details:
        joblist = [jobid
                   for roundlist in queues
                   for jobid in roundlist
                   if jobid is not None]
        return joblist
