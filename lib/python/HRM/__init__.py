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

JobSpooler()
    Spooler processing the job files.

HucoreDeconvolveApp()
HucorePreviewgenApp()
HucoreEstimateSNRApp()
    The gc3libs applications.
"""

# TODO: move gc3libs.Application classes to separate mocule
# TODO: don't transfer the image files, create a symlink or put their path
#       into the HuCore Tcl script
# TODO: catch exceptions on dispatching jobs, otherwise the QM gets stuck:
#       it stops watching the "new" spool directory if instantiating a
#       gc3libs.Application fails (resulting in a "dead" state right now),
#       instead a notification needs to be sent/printed to the user (later
#       this should trigger an email).

import ConfigParser
import pprint
import time
import os
import shutil
import itertools
import json
import logging

from collections import deque
from hashlib import sha1  # ignore this bug in pylint: disable=E0611

import gc3libs
from gc3libs.config import Configuration

# we set a default loglevel and add some shortcuts for logging:
LOGLEVEL = logging.WARN
gc3libs.configure_logger(LOGLEVEL, "qmgc3")
logw = gc3libs.log.warn
logi = gc3libs.log.info
logd = gc3libs.log.debug
loge = gc3libs.log.error
logc = gc3libs.log.critical

__all__ = ['JobDescription', 'JobQueue']


# expected version for job description files:
JOBFILE_VER = '7'

def setup_rundirs(base_dir):
    """Check if all runtime directories exist or try to create them otherwise.

    Assuming base_dir is '/run', the expected structure is like this:

    /run
        |-- queue
        |   |-- requests
        |   `-- status
        `-- spool
            |-- cur
            |-- done
            `-- new

    Parameters
    ----------
    base_dir : str
        Base path where to set up / check the run directories.

    Returns
    -------
    full_subdirs : {
        'new'      : '/run/spool/new',
        'cur'      : '/run/spool/cur',
        'done'     : '/run/spool/done',
        'requests' : '/run/queue/requests',
        'status'   : '/run/queue/status',
        'newfiles' : list of existing files in the 'new' directory
    }
    """
    full_subdirs = dict()
    tree = {
        'spool' : ['new', 'cur', 'done'],
        'queue' : ['status', 'requests']
    }
    for run_dir in tree:
        for sub_dir in tree[run_dir]:
            cur = os.path.join(base_dir, run_dir, sub_dir)
            if not os.access(cur, os.W_OK):
                if os.path.exists(cur):
                    raise OSError("Directory '%s' exists, but it is not "
                                  "writable for us. Stopping!" % cur)
                try:
                    os.makedirs(cur)
                    logi("Created spool directory '%s'.", cur)
                except OSError as err:
                    raise OSError("Error creating Queue Manager runtime "
                                  "directory '%s': %s" % (cur, err))
            full_subdirs[sub_dir] = cur

    # pick up any existing jobfiles in the 'new' spooldir
    full_subdirs['newfiles'] = list()
    new_existing = os.listdir(full_subdirs['new'])
    if new_existing:
        for fname in new_existing:
            logw("Found existing file in 'new' directory: %s", fname)
            full_subdirs['newfiles'].append(fname)
    logi("Runtime directories:\n%s", pprint.pformat(full_subdirs))

    # check the 'cur' directory and issue a warning only if non-empty:
    cur_existing = os.listdir(full_subdirs['cur'])
    if cur_existing:
        logw("%s WARNING %s", "=" * 60, "=" * 60)
        logw("Spooling directory '%s' non-empty, this could be due to an "
             "unclean shutdown of the Queue Manager!", full_subdirs['cur'])
        for fname in cur_existing:
            logw("- file: %s", os.path.join(full_subdirs['cur'], fname))
        logw("%s WARNING %s", "=" * 60, "=" * 60)
    return full_subdirs


def process_jobfile(fname, queues, dirs):
    """Parse a jobfile and add it to its destination queue.

    Parameters
    ----------
    fname : str
        The name of the job file to parse.
    queues : dict
        Containing the JobQueue objects for the different queues, using the
        corresponding 'type' keyword as identifier.
    dirs : dict
        Spooling directories in a dict, as returned by HRM.setup_rundirs().
    """
    try:
        job = JobDescription(fname, 'file')
        logd("Dict assembled from the processed job file:")
        logd(pprint.pformat(job))
    except IOError as err:
        logw("Error reading job description file (%s), skipping.", err)
        # there is nothing to add to the queue and the IOError indicates
        # problems accessing the file, so we simply return silently:
        return
    except (SyntaxError, ValueError) as err:
        logw("Job file unparsable (%s), skipping / moving to 'done'.", err)
        # still nothing to add to the queue but this time we can at least
        # move the file out of the way before returning:
        move_file(fname, dirs['done'], safe=True)
        return
    if not queues.has_key(job['type']):
        logc("ERROR: no queue existing for jobtype '%s'!", job['type'])
        move_file(fname, dirs['done'], safe=True)
        return
    job.move_jobfile(dirs['cur'])
    # TODO: we need to distinguish different job types and act accordingly
    queues[job['type']].append(job)


def move_file(fname, target, safe=False):
    """Helper function to move a file.

    Parameters
    ----------
    fname : str
        The original filename.
    target : str
        The target file or directory name.
    safe : bool
        If True, a timestamp will be added as a suffix to the filename in case
        the target already exists.
    """
    if safe:
        if os.path.exists(target):
            if os.path.isdir(target):
                target = os.path.join(target, fname)
            target += ".%s" % time.time()
    logd("Moving file '%s' to '%s'.", fname, target)
    shutil.move(fname, target)


class JobDescription(dict):

    """Abstraction class for handling HRM job descriptions.

    Read an HRM job description either from a file or a string and parse
    the sections, check them for sane values and store them in a dict.

    Instance Variables
    ------------------
    jobparser : ConfigParser.RawConfigParser
    srctype : str
    fname : str
    _sections : list
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
        super(JobDescription, self).__init__()
        self.jobparser = ConfigParser.RawConfigParser()
        self._sections = []
        self.srctype = srctype
        if srctype == 'file':
            self.fname = job
            self._parse_jobfile()
        elif srctype == 'string':
            self.fname = "string"
            # _parse_jobstring(job) needs to be implemented if required!
            raise Exception("Source type 'string' not yet implemented!")
        else:
            raise Exception("Unknown source type '%s'" % srctype)
        # store the SHA1 digest of this job, serving as the UID:
        # TODO: we could use be the hash of the actual (unparsed) string
        # instead of the representation of the Python object, but therefore we
        # need to hook into the parsing itself (or read the file twice) - this
        # way one could simply use the cmdline utility "sha1sum" to check if a
        # certain job description file belongs to a specific UID.
        self['uid'] = sha1(self.__repr__()).hexdigest()
        # after creating the UID, we fill in those keys that don't have a
        # reasonable value yet, they'll be updated later:
        self['status'] = "N/A"
        self['start'] = "N/A"
        self['progress'] = "N/A"
        self['pid'] = "N/A"
        self['server'] = "N/A"
        logi(pprint.pformat("Finished initialization of JobDescription()."))
        logd(pprint.pformat(self))

    def move_jobfile(self, target):
        """Move a jobfile to the desired spooling subdir.

        The file name will be set automatically to the job's UID with an
        added suffix ".jobfile", no matter how the file was called before.

        WARNING: destination file is not checked, if it exists and we have
        write permissions, it is simply overwritten!

        Parameters
        ----------
        target : str
            The target directory.
        """
        # make sure to only move "file" job descriptions, return otherwise:
        if self.srctype != 'file':
            return
        target = os.path.join(target, self['uid'] + '.jobfile')
        move_file(self.fname, target)
        # update the job's internal fname pointer:
        self.fname = target

    def _parse_jobfile(self):
        """Initialize ConfigParser for a file and run parsing method."""
        logd("Parsing jobfile '%s'...", self.fname)
        if not os.path.exists(self.fname):
            raise IOError("Can't find file '%s'!" % self.fname)
        if not os.access(self.fname, os.R_OK):
            raise IOError("No permission reading file '%s'!" % self.fname)
        # sometimes the inotify event gets processed very rapidly and we're
        # trying to parse the file *BEFORE* it has been written to disk
        # entirely, which breaks the parsing, so we introduce four additional
        # levels of waiting time to avoid this race condition:
        for snooze in [0, 0.001, 0.1, 1, 5]:
            if not self._sections and snooze > 0:
                logd("Sections are empty, re-trying in %is.", snooze)
            time.sleep(snooze)
            try:
                parsed = self.jobparser.read(self.fname)
                logd("Parsed file '%s'.", parsed)
            except ConfigParser.MissingSectionHeaderError as err:
                raise SyntaxError("ERROR in JobDescription: %s" % err)
            self._sections = self.jobparser.sections()
            if self._sections:
                logd("Job parsing succeeded after %s seconds!", snooze)
                break
        if not self._sections:
            raise SyntaxError("Can't parse '%s'" % self.fname)
        logd("Job description sections: %s", self._sections)
        self._parse_jobdescription()

    def _get_option(self, section, option):
        """Helper method to get an option and remove it from the section."""
        value = self.jobparser.get(section, option)
        self.jobparser.remove_option(section, option)
        return value

    def _check_for_remaining_options(self, section):
        """Helper method to check if a section has remaining items."""
        remaining = self.jobparser.items(section)
        if remaining:
            raise ValueError("Section '%s' in file '%s' contains unknown "
                             "options, jobfile is invalid: %s" %
                             (section, self.fname, remaining))

    def _parse_section_entries(self, section, options_mapping):
        """Helper function to read a given list of options from a section.

        Parameters
        ----------
        section : str
            The name of the section to parse.
        options_mapping : list of tuples
            A list of tuples containing the mapping from the option names in
            the config file to the key names in the JobDescription object, e.g.

            mapping = [
                ['version', 'ver'],
                ['username', 'user'],
                ['useremail', 'email'],
                ['timestamp', 'timestamp'],
                ['jobtype', 'type']
            ]
        """
        if not self.jobparser.has_section(section):
            raise ValueError("Error parsing job from %s." % self.fname)
        for cfg_option, job_key in options_mapping:
            try:
                self[job_key] = self._get_option(section, cfg_option)
            except ConfigParser.NoOptionError:
                raise ValueError("Can't find %s in %s." %
                                 (cfg_option, self.fname))
                # raise ValueError("Jobfile %s invalid, '%s' is missing!" %
                #                  (self.fname, cfg_option))
        ### by now the section should be fully parsed and therefore empty:
        self._check_for_remaining_options('hrmjobfile')

    def _parse_jobdescription(self):
        """Parse details for an HRM job and check for sanity.

        Use the ConfigParser object and assemble a dicitonary with the
        collected details that contains all the information for launching a new
        processing task. Raises Exceptions in case something unexpected is
        found in the given file.
        """
        ### prepare the parser-mapping for the generic 'hrmjobfile' section:
        mapping = [
            ['version', 'ver'],
            ['username', 'user'],
            ['useremail', 'email'],
            ['timestamp', 'timestamp'],
            ['jobtype', 'type']
        ]
        ### now parse the section:
        self._parse_section_entries('hrmjobfile', mapping)
        ### sanity-check / validate the parsed options:
        # version
        if self['ver'] != JOBFILE_VER:
            raise ValueError("Unexpected jobfile version '%s'." % self['ver'])
        # timestamp
        if self['timestamp'] == 'on_parsing':
            # the keyword "on_parsing" requires us to fill in the value:
            self['timestamp'] = time.time()
        else:
            # otherwise we need to convert to float, or raise an error:
            try:
                self['timestamp'] = float(self['timestamp'])
            except ValueError:
                raise ValueError("Invalid timestamp: %s." % self['timestamp'])
        ### now call the jobtype-specific parser method(s):
        # FIXME: currently only deconvolution jobs are supported, until hucore
        # will be able to do the other things like SNR estimation and
        # previewgen using templates as well!
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
        ### prepare the parser-mapping for the generic 'hrmjobfile' section:
        mapping = [
            ['tasktype', 'tasktype'],
            ['executable', 'exec'],
            ['template', 'template']
        ]
        ### now parse the section:
        self._parse_section_entries('hucore', mapping)
        # and the input file(s) section:
        # TODO: can we check if this section contains nonsense values?
        if 'inputfiles' not in self._sections:
            raise ValueError("No input files defined in %s." % self.fname)
        self['infiles'] = []
        for option in self.jobparser.options('inputfiles'):
            infile = self._get_option('inputfiles', option)
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
        """
        self.statusfile = None
        self.cats = deque('')
        self.jobs = dict()
        self.processing = list()
        self.queue = dict()

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
        if self.jobs.has_key(uid):
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

    def _is_queue_empty(self, cat):
        """Clean up if a queue of a given category is empty.

        Return True if the queue was empty and removed, False otherwise.
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

    def remove(self, uid):
        """Remove a job with a given UID from the queue.

        Take a job UID and remove the job from the list of currently processing
        jobs or its category's queue, cleaning up the queue if necessary.

        Parameters
        ----------
        uid : str (UID of job to remove)

        Returns
        -------
        job : JobDescription
            The JobDescription dict of the job that was removed (on success).
        """
        logd("Trying to remove job with uid '%s'.", uid)
        if not self.jobs.has_key(uid):
            logw("No job with uid '%s' was found!", uid)
            return None
        job = self.jobs[uid]   # remember the job for returning it later
        cat = job.get_category()
        del self.jobs[uid]     # remove the job from the jobs dict
        if self.queue.has_key(cat) and uid in self.queue[cat]:
            logd("Removing job '%s' from queue '%s'.", uid, cat)
            self.queue[cat].remove(uid)
            self._is_queue_empty(cat)
        elif uid in self.processing:
            logd("Removing job '%s' from currently processing jobs.", uid)
            self.processing.remove(uid)
        else:
            logw("Can't find job '%s' in any of our queues!", uid)
            return None
        logd("Current jobs: %s", self.jobs)
        logd("Current queue categories: %s", self.cats)
        logd("Current contents of all queues: %s", self.queue)
        return job

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
                "id" : job['uid'],
                "file" : job['infiles'][0],  # TODO: see above!
                "username" : job['user'],
                "jobType" : job['type'],
                "status" : job['status'],
                "server" : 'N/A',
                "progress" : 'N/A',
                "pid" : 'N/A',
                "start" : 'N/A',
                "queued" : job['timestamp'],
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


class JobSpooler(object):

    """Spooler class processing the queue, dispatching jobs, etc.

    Instance Variables
    ------------------
    gc3spooldir : str
    gc3conf : str
    dirs : dict
    engine : gc3libs.core.Engine
    """

    def __init__(self, spool_dirs, queue, gc3conf=None):
        """Prepare the spooler.

        Check the GC3Pie config file, set up the spool directories, set up the
        gc3 engine, check the resource directories.

        Parameters
        ----------
        spool_dirs : dict
            Spooling directories in a dict, as returned by setup_rundirs().
        queue : HRM.JobQueue
        gc3conf : str
            The path to a gc3pie configuration file.
        """
        self.queue = queue
        self.gc3spooldir = None
        self.gc3conf = None
        self._check_gc3conf(gc3conf)
        self.dirs = spool_dirs
        self.engine = self.setup_engine()
        if not self.resource_dirs_clean():
            raise RuntimeError("GC3 resource dir unclean, refusing to start!")
        # the default status is 'run' unless explicitly requested (which will
        # be respected by the _spool() function anyway):
        self.status_pre = self.status_cur = 'run'

    def _check_gc3conf(self, gc3conffile=None):
        """Check the gc3 config file and extract the gc3 spooldir.

        Helper method to check the config file and set the instance variables
        self.gc3spooldir : str
            The path name to the gc3 spooling directory.
        self.gc3conf : str
            The file NAME of the gc3 config file.
        """
        # gc3libs methods like create_engine() use the default config in
        # ~/.gc3/gc3pie.conf if none is specified (see API for details)
        if gc3conffile is None:
            gc3conffile = '~/.gc3/gc3pie.conf'
        gc3conf = Configuration(gc3conffile)
        try:
            self.gc3spooldir = gc3conf.resources['localhost'].spooldir
        except AttributeError:
            raise AttributeError("Unable to parse spooldir for resource "
                                 "'localhost' from gc3pie config file '%s'!" %
                                 gc3conffile)
        self.gc3conf = gc3conffile

    def setup_engine(self):
        """Set up the GC3Pie engine.

        Returns
        -------
        gc3libs.core.Engine
        """
        logi('Creating GC3Pie engine using config file "%s".', self.gc3conf)
        return gc3libs.create_engine(self.gc3conf)

    def select_resource(self, resource):
        """Select a specific resource for the GC3Pie engine."""
        self.engine.select_resource(resource)

    def resource_dirs_clean(self):
        """Check if the resource dirs of all resources are clean.

        Parameters
        ----------
        engine : gc3libs.core.Engine
            The GC3 engine to check the resource directories for.

        Returns
        -------
        bool
        """
        # NOTE: with the session-based GC3 approach, it should be possible to
        # pick up existing (leftover) jobs in a resource directory upon start
        # and figure out what their status is, clean up, collect results etc.
        for resource in self.engine.get_resources():
            resourcedir = os.path.expandvars(resource.cfg_resourcedir)
            logi("Checking resource dir for resource '%s': %s",
                 resource.name, resourcedir)
            if not os.path.exists(resourcedir):
                continue
            files = os.listdir(resourcedir)
            if files:
                logw("Resource dir unclean: %s", files)
                return False
        return True

    def check_status_request(self):
        """Check if a status change for the QM was requested."""
        valid = ['shutdown', 'refresh', 'pause', 'run']
        for fname in valid:
            check_file = os.path.join(self.dirs['requests'], fname)
            if os.path.exists(check_file):
                os.remove(check_file)
                self.status_pre = self.status_cur
                self.status_cur = fname
                logw("Received queue request: %s -> %s",
                     self.status_pre, self.status_cur)
                # we don't process more than one request at a time, so exit:
                return

    def spool(self):
        """Wrapper method for the spooler to catch Ctrl-C."""
        # TODO: when the spooler gets stopped (e.g. via Ctrl-C or upon request
        # from the web interface or the init script) while a job is still
        # running, it leaves it alone (and thus as well the files transferred
        # for / generated from processing)
        try:
            self._spool()
        except KeyboardInterrupt:
            logi("Received keyboard interrupt, stopping queue manager.")

    def _spool(self):
        """Spooler function dispatching jobs from the queues. BLOCKING!"""
        apps = []
        while True:
            self.check_status_request()
            if self.status_cur == 'run':
                self.engine.progress()
                for i, app in enumerate(apps):
                    new_state = app.status_changed()
                    if new_state is not None:
                        self.queue.set_jobstatus(app.job, new_state)
                    if new_state == gc3libs.Run.State.TERMINATED:
                        app.job.move_jobfile(self.dirs['done'])
                        apps.pop(i)
                stats = self._engine_status()
                # NOTE: in theory, we could simply add all apps to the engine
                # and let gc3 decide when to dispatch the next one, however
                # this it is causing a lot of error messages if the engine has
                # more tasks than available resources, see HRM ticket #421 and
                # upstream gc3pie ticket #359 for more details. For now we do
                # not submit new jobs if there are any running or submitted:
                if stats['RUNNING'] > 0 or stats['SUBMITTED'] > 0:
                    time.sleep(1)
                    continue
                nextjob = self.queue.next_job()
                if nextjob is not None:
                    logd("Current joblist: %s", self.queue.queue)
                    logi("Adding another job to the gc3pie engine.")
                    app = HucoreDeconvolveApp(nextjob, self.gc3spooldir)
                    apps.append(app)
                    self.engine.add(app)
                    # as a new job is dispatched now, we also print out the
                    # human readable queue status:
                    print self.queue.queue_details_hr()
            elif self.status_cur == 'shutdown':
                return True
            elif self.status_cur == 'refresh':
                # self.queue.queue_details_hr()
                print self.queue.queue_details_hr()
                logd(self.queue.queue_details_json())
                self.status_cur = self.status_pre
            elif self.status_cur == 'pause':
                # no need to do anything, just sleep and check requests again:
                pass
            time.sleep(1)

    def _engine_status(self):
        """Helper to get the engine status and print a formatted log."""
        stats = self.engine.stats()
        logd("Engine: NEW:%s  SUBM:%s  RUN:%s  TERM'ing:%s  TERM'ed:%s  "
             "UNKNWN:%s  STOP:%s  (total:%s)",
             stats['NEW'], stats['SUBMITTED'], stats['RUNNING'],
             stats['TERMINATING'], stats['TERMINATED'], stats['UNKNOWN'],
             stats['STOPPED'], stats['total'])
        return stats


class HucoreDeconvolveApp(gc3libs.Application):

    """App object for 'hucore' deconvolution jobs.

    This application calls `hucore` with a given template file and retrives the
    stdout/stderr in a file named `stdout.txt` plus the directories `resultdir`
    and `previews` into a directory `deconvolved` inside the current directory.
    """

    def __init__(self, job, gc3_output):
        self.job = job   # remember the job object
        uid = self.job['uid']
        logw('Instantiating a HucoreDeconvolveApp:\n[%s]: %s --> %s',
             self.job['user'], self.job['template'], self.job['infiles'])
        logi('Job UID: %s', uid)
        # we need to add the template (with the local path) to the list of
        # files that need to be transferred to the system running hucore:
        self.job['infiles'].append(self.job['template'])
        # for the execution on the remote host, we need to strip all paths from
        # this string as the template file will end up in the temporary
        # processing directory together with all the images:
        templ_on_tgt = self.job['template'].split('/')[-1]
        gc3libs.Application.__init__(
            self,
            arguments=[self.job['exec'],
                       '-exitOnDone',
                       '-noExecLog',
                       '-checkForUpdates', 'disable',
                       '-template', templ_on_tgt],
            inputs=self.job['infiles'],
            outputs=['resultdir', 'previews'],
            # collect the results in a subfolder of GC3Pie's spooldir:
            output_dir=os.path.join(gc3_output, 'results_%s' % uid),
            stderr='stdout.txt', # combine stdout & stderr
            stdout='stdout.txt'
        )
        self.laststate = self.execution.state

    def terminated(self):
        """This is called when the app has terminated execution."""
        # TODO: put the results dir back to the user's destination directory
        # (WARNING: we have to be careful if the data has already been
        # collected in case of a remote execution scenario)
        # TODO: consider specifying the output dir in the jobfile!
        # -> for now we simply use the gc3spooldir as the output directory to
        # ensure results won't get moved across different storage locations:
        # hucore EXIT CODES:
        # 0: all went well
        # 143: hucore.bin received the HUP signal (9)
        # 165: the .hgsb file could not be parsed (file missing or with errors)
        if self.execution.exitcode != 0:
            logc("Job '%s' terminated with unexpected EXIT CODE: %s!",
                 self.job['uid'], self.execution.exitcode)
        else:
            logi("Job '%s' terminated successfully!", self.job['uid'])
        logd("The output of the application is in `%s`.", self.output_dir)

    def status_changed(self):
        """Check the if the execution state of the app has changed.

        Track and update the internal execution status of the app and print a
        log message if the status changes. Return the new state if the app it
        has changed, otherwise None.
        """
        if self.execution.state != self.laststate:
            logi("Job status changed to '%s'.", self.job['status'])
            self.laststate = self.execution.state
            return self.execution.state
        else:
            return None


class HucorePreviewgenApp(gc3libs.Application):

    """App object for 'hucore' image preview generation jobs."""

    def __init__(self):
        # logw('Instantiating a HucorePreviewgenApp:\n%s', job)
        logw('WARNING: this is a stub, nothing is implemented yet!')
        super(HucorePreviewgenApp, self).__init__()


class HucoreEstimateSNRApp(gc3libs.Application):

    """App object for 'hucore' SNR estimation jobs."""

    def __init__(self):
        # logw('Instantiating a HucoreEstimateSNRApp:\n%s', job)
        logw('WARNING: this is a stub, nothing is implemented yet!')
        super(HucoreEstimateSNRApp, self).__init__()
