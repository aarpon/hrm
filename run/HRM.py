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
from collections import deque
from hashlib import sha1

from hrm_logger import warn, info, debug, set_loglevel

__all__ = ['JobDescription', 'JobQueue']


# expected version for job description files:
JOBFILE_VER = '3'

def deq_del(d, n):
    """Delete the n'th element of a deque object."""
    d.rotate(-n)
    d.popleft()
    d.rotate(n)


class JobDescription(dict):

    """Abstraction class for handling HRM job descriptions.

    Read an HRM job description either from a file or a string and parse
    the sections, check them for sane values and store them in a dict.
    """

    def __init__(self, job, srctype):
        """Initialize depending on the type of description source.

        Parameters
        ----------
        job : string
        srctype : string

        Example
        -------
        >>> job = HRM.JobDescription('/path/to/jobdescription.cfg', 'file')
        """
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
        self.jobparser.read(fname)
        self._parse_jobdescription()

    def _parse_jobdescription(self):
        """Parse details for an HRM job and check for sanity.

        Use the ConfigParser object and assemble a dicitonary with the
        collected details that contains all the information for launching a new
        processing task. Raises Exceptions in case something unexpected is
        found in the given file.
        """
        # FIXME: currently only deconvolution jobs are supported, until hucore
        # will be able to do the other things like SNR estimation and
        # previewgen using templates as well!
        # TODO: group code into parsing and sanity-checking
        self._sections = self.jobparser.sections()
        # parse generic information, version, user etc.
        if not 'hrmjobfile' in self._sections:
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

    # TODO: implement len() or similar
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
        """Return the next job description for processing."""
        # TODO: catch an empty categories queue
        cat = self.cats[0]
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
        """Remove a job with a given UID from the queue."""
        # TODO: cover non-existing UID's
        warn("Trying to remove job with uid '%s'." % uid)
        cat = self.jobs[uid].get_category()
        debug("Category of job to remove: '%s'." % cat)
        self.queue[cat].remove(uid)
        debug("Current queue categories: %s" % self.cats)
        debug("Current contents of all queues: %s" % self.queue)
        if len(self.queue[cat]) < 1:
            debug("Queue for category '%s' now empty, removing it." % cat)
            self.cats.remove(cat)  # remove it from the categories list
            del self.queue[cat]    # delete the category from the queue dict
            debug("Current queue categories: %s" % self.cats)
            debug("Current contents of all queues: %s" % self.queue)
