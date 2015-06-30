#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Support for various HRM related tasks.

Classes
-------

JobDescription()
    Parser for job descriptions, works on files or strings.

JobQueue()
    Job handling and scheduling.
"""

import ConfigParser
import pprint
import time
from collections import deque
from hashlib import sha1

from hrm_logger import warn, info, debug, set_loglevel

__all__ = ['JobDescription', 'JobQueue']


# expected version for job description files:
JOBFILE_VER = '4'


class JobDescription(dict):

    """Abstraction class for handling HRM job descriptions.

    Read an HRM job description either from a file or a string and parse
    the sections, check them for sane values and store them in a dict.
    """

    def __init__(self, job, srctype, loglevel=None):
        """Initialize depending on the type of description source.

        Parameters
        ----------
        job : string
        srctype : string

        Example
        -------
        >>> job = HRM.JobDescription('/path/to/jobdescription.cfg', 'file')
        """
        super(JobDescription, self).__init__()
        if loglevel is not None:
            set_loglevel(loglevel)
        self.jobparser = ConfigParser.RawConfigParser()
        self._sections = []
        if (srctype == 'file'):
            self.name = "file '%s'" % job
            self._parse_jobfile(job)
        elif (srctype == 'string'):
            # TODO: _parse_jobstring(job)
            self.name = "string received from socket"
            raise Exception("Source type 'string' not yet implemented!")
        else:
            raise Exception("Unknown source type '%s'" % srctype)
        # store the SHA1 digest of this job, serving as the UID:
        # TODO: this should better be the hash of the actual (unparsed) string
        # instead of the representation of the Python object, but therefore
        # we need to hook into the parsing itself (or read the file twice).
        self['uid'] = sha1(self.__repr__()).hexdigest()
        pprint.pprint("Finished initialization of JobDescription().")
        pprint.pprint(self)

    def _parse_jobfile(self, fname):
        """Initialize ConfigParser for a file and run parsing method."""
        debug("Parsing jobfile '%s'..." % fname)
        # sometimes the inotify event gets processed very rapidly and we're
        # trying to parse the file *BEFORE* it has been written to disk
        # entirely, which breaks the parsing, so we introduce two additional
        # levels of waiting time to avoid this race condition:
        for snooze in [0, 1, 5]:
            if not self._sections and snooze > 0:
                info("Sections are empty, re-trying in %is." % snooze)
            time.sleep(snooze)
            try:
                parsed = self.jobparser.read(fname)
                debug("Parsed file '%s'." % parsed)
            except ConfigParser.MissingSectionHeaderError as e:
                # consider using SyntaxError here!
                raise IOError("ERROR in JobDescription: %s" % e)
            self._sections = self.jobparser.sections()
            if self._sections:
                continue
        if not self._sections:
            warn("ERROR: Could not parse '%s'!" % fname)
            raise IOError("Can't parse '%s'" % fname)
        debug("Job description sections: %s" % self._sections)
        self._parse_jobdescription()

    def _parse_jobdescription(self):
        """Parse details for an HRM job and check for sanity.

        Use the ConfigParser object and assemble a dicitonary with the
        collected details that contains all the information for launching a new
        processing task. Raises Exceptions in case something unexpected is
        found in the given file.
        """
        # TODO: group code into parsing and sanity-checking
        # FIXME: currently only deconvolution jobs are supported, until hucore
        # will be able to do the other things like SNR estimation and
        # previewgen using templates as well!
        # parse generic information, version, user etc.
        if not self.jobparser.has_section('hrmjobfile'):
            raise ValueError("Error parsing job from %s." % self.name)
        try:
            self['ver'] = self.jobparser.get('hrmjobfile', 'version')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find version in %s." % self.name)
        if not (self['ver'] == JOBFILE_VER):
            raise ValueError("Unexpected version in %s." % self['ver'])
        try:
            self['user'] = self.jobparser.get('hrmjobfile', 'username')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find username in %s." % self.name)
        try:
            self['email'] = self.jobparser.get('hrmjobfile', 'useremail')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find email address in %s." % self.name)
        try:
            self['type'] = self.jobparser.get('hrmjobfile', 'jobtype')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find jobtype in %s." % self.name)
        # from here on a jobtype specific parsing must be done:
        if self['type'] == 'hucore':
            self._parse_job_hucore()
        else:
            raise ValueError("Unknown jobtype '%s'" % self['type'])

    def _parse_job_hucore(self):
        """Do the specific parsing of "hucore" type jobfiles.

        Parse the "hucore" and the "inputfiles" sections of HRM job
        configuration files.

        Returns
        -------
        void
            All information is added to the "self" dict.
        """
        # the "hucore" section:
        try:
            self['exec'] = self.jobparser.get('hucore', 'executable')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find executable in %s." % self.name)
        try:
            self['template'] = self.jobparser.get('hucore', 'template')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find template in %s." % self.name)
        # and the input file(s):
        if not 'inputfiles' in self._sections:
            raise ValueError("No input files defined in %s." % self.name)
        self['infiles'] = []
        for option in self.jobparser.options('inputfiles'):
            infile = self.jobparser.get('inputfiles', option)
            self['infiles'].append(infile)

    def get_category(self):
        """Get the category of this job, in our case the value of 'user'."""
        return self['user']


class JobQueue(object):

    """Class to store a list of jobs that need to be processed.

    An instance of this class can be used to keep track of lists of jobs of
    different categories (e.g. individual users). The instance will contain a
    scheduler so that it is possible for the caller to simply request the next
    job from this queue without having to care about priorities or anything
    else.
    """

    # TODO: implement __len__()
    # TODO: either remove items from jobs[] upon pop() / remove() or add their
    # ID to a list so the jobs[] dict can get garbage-collected later
    def __init__(self):
        """Initialize an empty job queue."""
        self.cats = deque('')  # categories / users, used by the scheduler
        # jobs is a dict containing the JobDescription objects using their
        # UID as the indexing key for fast access:
        self.jobs = dict()
        self.queue = dict()

    def append(self, job):
        """Add a new job to the queue."""
        # TODO: should we catch duplicate jobs? Currently they are enqueued.
        cat = job.get_category()
        uid = job['uid']
        info("Enqueueing job '%s' into category '%s'." % (uid, cat))
        self.jobs[uid] = job  # store the job in the global dict
        if not cat in self.cats:
            warn("Adding a new queue for '%s' to the JobQueue." % cat)
            self.cats.append(cat)
            self.queue[cat] = deque()
            debug("Current queue categories: %s" % self.cats)
        else:
            # in case there are already jobs of this category, we don't touch
            # the scheduler / priority queue:
            debug("JobQueue already contains a queue for '%s'." % cat)
        self.queue[cat].append(uid)
        info("Queue for category '%s': %s" % (cat, self.queue[cat]))
        # debug("Overall list of job descriptions: %s" % self.jobs)

    def pop(self):
        """Return the next job description for processing.

        Picks the next that should be processed from that queue that has the
        topmost position in the categories queue. After selecting the job, the
        categories queue is shifted one to the left, meaning that the category
        of the just picked job is then at the last position in the categories
        queue.
        This implements a very simple round-robin (token based) scheduler that
        is going one-by-one through the existing categories.
        """
        try:
            cat = self.cats[0]
        except IndexError as e:
            warn('Categories queue is empty, no jobs left!')
            return
        jobid = self.queue[cat].popleft()
        info("Retrieving next job: category '%s', uid '%s'." % (cat, jobid))
        if len(self.queue[cat]) >= 1:
            debug("Shifting category list.")
            self.cats.rotate(-1)  # move the first element to last position
        else:
            debug("Queue for category '%s' now empty, removing it." % cat)
            self.cats.popleft()  # remove it from the categories list
            del self.queue[cat]  # delete the category from the queue dict
        debug("Current queue categories: %s" % self.cats)
        debug("Current contents of all queues: %s" % self.queue)
        return self.jobs[jobid]

    def remove(self, uid):
        """Remove a job with a given UID from the queue.

        Take a job UID, look up the corresponding category for this job and
        remove the job from this category's queue. If this queue is empty
        afterwards, clean up by removing the job's category from the categories
        list and deleting the category deque from the queue dict.

        Parameters
        ----------
        uid : str (UID of job to remove)
        """
        warn("Trying to remove job with uid '%s'." % uid)
        try:
            cat = self.jobs[uid].get_category()
        except KeyError as e:
            warn("No job with uid '%s' was found!" % e)
            return
        debug("Category of job to remove: '%s'." % cat)
        try:
            self.queue[cat].remove(uid)
        except KeyError as e:
            warn("No queue for category %s was found!" % e)
            return
        except ValueError as e:
            warn("No job with uid '%s' in queue! (%s)" % (uid, e))
            return
        debug("Current queue categories: %s" % self.cats)
        debug("Current contents of all queues: %s" % self.queue)
        if len(self.queue[cat]) < 1:
            debug("Queue for category '%s' now empty, removing it." % cat)
            self.cats.remove(cat)  # remove it from the categories list
            del self.queue[cat]    # delete the category from the queue dict
            debug("Current queue categories: %s" % self.cats)
            debug("Current contents of all queues: %s" % self.queue)

    def queue_details_hr(self):
        """Generate a human readable list with the current queue details."""
        ci = 0  # pointer for categories
        cmax = len(self.cats)  # number of categories
        cdone = 0
        print('Queue categories: %i' % cmax)
        queues = dict()
        for i in range(len(self.cats)):
            # jobid = self.queue[self.cats[i]]
            queues[self.cats[i]] = 0  # pointers to jobs in separate categories
        print(queues)
        while True:
            cat = self.cats[ci]
            # print("Current category: %i (%s)" % (ci, cat))
            curqueue = self.queue[cat]
            # print("Current queue: %s" % curqueue)
            # print("Current in-queue pointers: %s" % queues)
            if queues[cat] > -1:
                jobid = curqueue[queues[cat]]
                print("Next job id: %s" % jobid)
                queues[cat] += 1
                if queues[cat] >= len(self.queue[cat]):
                    queues[cat] = -1
                    cdone += 1  # increase counter of processed categories
                    if cdone == cmax:
                        return
            ci += 1
            if ci >= cmax: ci = 0
            # print("Category pointer: %i" % ci)
            # print("Current in-queue pointers: %s" % queues)
