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
import sys
import shutil
import itertools
import json
from collections import deque
from hashlib import sha1  # ignore this bug in pylint: disable-msg=E0611

# GC3Pie imports
try:
    import gc3libs
except ImportError:
    print("ERROR: unable to import GC3Pie library package, please make sure")
    print("it is installed and active, e.g. by running this command before")
    print("starting the HRM Queue Manager:")
    print("\n$ source /path/to/your/gc3pie_installation/bin/activate\n")
    sys.exit(1)

from gc3libs.config import Configuration

from hrm_logger import warn, info, debug, set_loglevel

import logging
# we set a default loglevel and add some shortcuts for logging:
loglevel = logging.WARN
gc3libs.configure_logger(loglevel, "qmgc3")
logw = gc3libs.log.warn
logi = gc3libs.log.info
logd = gc3libs.log.debug
loge = gc3libs.log.error
logc = gc3libs.log.critical

__all__ = ['JobDescription', 'JobQueue']


# expected version for job description files:
JOBFILE_VER = '5'

def setup_rundirs(base_dir):
    """Check if all runtime directories exist or try to create them otherwise.

    Assuming base_dir is '/run/hrm', the expected structure is like this:

    /run/hrm
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
    full_subdirs : dict
        { 'new'      : '/run/hrm/spool/new',
          'cur'      : '/run/hrm/spool/cur',
          'done'     : '/run/hrm/spool/done',
          'requests' : '/run/hrm/queue/requests',
          'status'   : '/run/hrm/queue/status' }
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
                    logi("Created spool directory '%s'." % cur)
                except OSError as err:
                    raise OSError("Error creating Queue Manager runtime "
                        "directory '%s': %s" % (cur, err))
            full_subdirs[sub_dir] = cur
    logd("Runtime directories:\n%s" % pprint.pformat(full_subdirs))
    return full_subdirs


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
        self.srctype = srctype
        if (srctype == 'file'):
            self.fname = job
            self._parse_jobfile()
        elif (srctype == 'string'):
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
        if target is None:
            logw("No target directory set, not moving job file!")
            return
        target = os.path.join(target, self['uid'] + '.jobfile')
        logd("Moving jobfile '%s' to '%s'." % (self.fname, target))
        shutil.move(self.fname, target)
        self.fname = target

    def _parse_jobfile(self):
        """Initialize ConfigParser for a file and run parsing method."""
        logd("Parsing jobfile '%s'..." % self.fname)
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
                info("Sections are empty, re-trying in %is." % snooze)
            time.sleep(snooze)
            try:
                parsed = self.jobparser.read(self.fname)
                logd("Parsed file '%s'." % parsed)
            except ConfigParser.MissingSectionHeaderError as err:
                # consider using SyntaxError here!
                raise IOError("ERROR in JobDescription: %s" % err)
            self._sections = self.jobparser.sections()
            if self._sections:
                logd("Job parsing succeeded after %s seconds!" % snooze)
                break
        if not self._sections:
            raise IOError("Can't parse '%s'" % self.fname)
        logd("Job description sections: %s" % self._sections)
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
            raise ValueError("Error parsing job from %s." % self.fname)
        # version
        try:
            self['ver'] = self.jobparser.get('hrmjobfile', 'version')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find version in %s." % self.fname)
        if not (self['ver'] == JOBFILE_VER):
            raise ValueError("Unexpected version in %s." % self['ver'])
        # username
        try:
            self['user'] = self.jobparser.get('hrmjobfile', 'username')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find username in %s." % self.fname)
        # useremail
        try:
            self['email'] = self.jobparser.get('hrmjobfile', 'useremail')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find email address in %s." % self.fname)
        # timestamp
        try:
            self['timestamp'] = self.jobparser.get('hrmjobfile', 'timestamp')
            # the keyword "on_parsing" requires us to fill in the value:
            if self['timestamp'] == 'on_parsing':
                self['timestamp'] = time.time()
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find timestamp in %s." % self.fname)
        # type
        try:
            self['type'] = self.jobparser.get('hrmjobfile', 'jobtype')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find jobtype in %s." % self.fname)
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
            raise ValueError("Can't find executable in %s." % self.fname)
        try:
            self['template'] = self.jobparser.get('hucore', 'template')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find template in %s." % self.fname)
        # and the input file(s):
        if not 'inputfiles' in self._sections:
            raise ValueError("No input files defined in %s." % self.fname)
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
            logd("Current queue categories: %s" % self.cats)
        else:
            # in case there are already jobs of this category, we don't touch
            # the scheduler / priority queue:
            logd("JobQueue already contains a queue for '%s'." % cat)
        self.queue[cat].append(uid)
        info("Queue for category '%s': %s" % (cat, self.queue[cat]))
        # logd("Overall list of job descriptions: %s" % self.jobs)

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
        except IndexError:
            warn('Categories queue is empty, no jobs left!')
            return
        jobid = self.queue[cat].popleft()
        info("Retrieving next job: category '%s', uid '%s'." % (cat, jobid))
        if len(self.queue[cat]) >= 1:
            logd("Shifting category list.")
            self.cats.rotate(-1)  # move the first element to last position
        else:
            logd("Queue for category '%s' now empty, removing it." % cat)
            self.cats.popleft()  # remove it from the categories list
            del self.queue[cat]  # delete the category from the queue dict
        logd("Current queue categories: %s" % self.cats)
        logd("Current contents of all queues: %s" % self.queue)
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
        except KeyError as err:
            warn("No job with uid '%s' was found!" % err)
            return
        logd("Category of job to remove: '%s'." % cat)
        try:
            self.queue[cat].remove(uid)
        except KeyError as err:
            warn("No queue for category %s was found!" % err)
            return
        except ValueError as err:
            warn("No job with uid '%s' in queue! (%s)" % (uid, err))
            return
        logd("Current queue categories: %s" % self.cats)
        logd("Current contents of all queues: %s" % self.queue)
        if len(self.queue[cat]) < 1:
            logd("Queue for category '%s' now empty, removing it." % cat)
            self.cats.remove(cat)  # remove it from the categories list
            del self.queue[cat]    # delete the category from the queue dict
            logd("Current queue categories: %s" % self.cats)
            logd("Current contents of all queues: %s" % self.queue)

    def queue_details_json(self, jsonfile=None):
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

        If the parameter "jsonfile" is given, the details are also written to
        this file.
        """
        joblist = self.queue_details()
        formatted = []
        for job in joblist:
            fjob = {
                "id" : job['uid'],
                "file" : job['infiles'],
                "username" : job['user'],
                "jobType" : job['type'],
                "status" : 'N/A',
                "server" : 'N/A',
                "progress" : 'N/A',
                "pid" : 'N/A',
                "start" : 'N/A',
                "queued" : job['timestamp'],
            }
            formatted.append(fjob)
        details = {'jobs' : formatted}
        if jsonfile is not None:
            with open(jsonfile, 'w') as fout:
                json.dump(details, fout)
        return json.dumps(details, indent=4)


    def queue_details_hr(self):
        """Generate a human readable list of the queue details."""
        joblist = self.queue_details()
        print "-" * 25, " queue status ", "-" * 25
        for job in joblist:
            print("%s (%s): %s - %s" %
                (job['user'], job['email'], job['uid'], job['infiles']))
        print "-" * 25, " queue status ", "-" * 25

    def queue_details(self):
        """Generate a list with the current queue details.

        For now this simply interleaves all queues from all users, until we
        have implemented a more sophisticated scheduling. However, as the plan
        is to have a dynamic scheduling mechanism, the order of the jobs in
        the queue will be subject to constant change - and therefore the
        queue details will in the best case give an estimate of which jobs
        will be run next.

        Example
        -------
        Given the following queue status:
        self.queue = {'user00': deque(['u00_j0', 'u00_j1', 'u00_j2', 'u00_j3']),
                      'user01': deque(['u01_j0', 'u01_j1', 'u01_j2']),
                      'user02': deque(['u02_j0', 'u02_j1'])}

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
        # create a zipped list of the queues of all users, padding with None
        # to compensate the different queue lengths:
        queues = map(None, *self.queue.values())
        # using the example values from above, this results in the following:
        # [('u02_j0', 'u01_j0', 'u00_j0'),
        #  ('u02_j1', 'u01_j1', 'u00_j1'),
        #  (None,     'u01_j2', 'u00_j2'),
        #  (None,     None,     'u00_j3')]

        # now we can simply use itertools to flatten the tuple-list:
        joblist = []
        for jobid in itertools.chain.from_iterable(queues):
            if jobid is not None:
                joblist.append(self.jobs[jobid])
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

    def __init__(self, spool_dir, gc3conf=None):
        """Prepare the spooler.

        Check the GC3Pie config file, set up the spool directories, set up the
        gc3 engine, check the resource directories.
        """
        self.gc3spooldir = None
        self.gc3conf = None
        self._check_gc3conf(gc3conf)
        self.dirs = setup_rundirs(spool_dir)
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
                "'localhost' from gc3pie config file '%s'!" % gc3conffile)
        self.gc3conf = gc3conffile

    def setup_engine(self):
        """Set up the GC3Pie engine.

        Returns
        -------
        gc3libs.core.Engine
        """
        logi('Creating GC3Pie engine using config file "%s".' % self.gc3conf)
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
            logi("Checking resource dir for resource '%s': %s" %
                (resource.name, resourcedir))
            if not os.path.exists(resourcedir):
                continue
            files = os.listdir(resourcedir)
            if files:
                logw("Resource dir unclean: %s" % files)
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
                logi("Received queue request: %s -> %s" %
                    (self.status_pre, self.status_cur))
                # we don't process more than one request at a time, so exit:
                return

    def spool(self, jobqueues):
        """Wrapper method for the spooler to catch Ctrl-C."""
        # TODO: when the spooler gets stopped (e.g. via Ctrl-C or upon request
        # from the web interface or the init script) while a job is still
        # running, it leaves it alone (and thus as well the files transferred
        # for / generated from processing)
        try:
            self._spool(jobqueues)
        except KeyboardInterrupt:
            logi("Received keyboard interrupt, stopping queue manager.")

    def _spool(self, jobqueues):
        """Spooler function dispatching jobs from the queues. BLOCKING!"""
        apps = []
        while True:
            self.check_status_request()
            if self.status_cur == 'run':
                self.engine.progress()
                running_jobs = self.engine.stats()['RUNNING']
                for i, app in enumerate(apps):
                    if app.has_finished():
                        app.job.move_jobfile(self.dirs['done'])
                        apps.pop(i)
                nextjob = jobqueues['hucore'].pop()
                if nextjob is not None:
                    logi("Current joblist: %s" % jobqueues['hucore'].queue)
                    logi("Adding another job to the gc3pie engine.")
                    app = HucoreDeconvolveApp(nextjob, self.gc3spooldir)
                    apps.append(app)
                    # WARNING: simply adding all apps to the engine does work,
                    # but it is causing a lot of error messages if the engine
                    # has more tasks than available resources, see HRM ticket
                    # #421 and upstream gc3pie ticket #359 for more details.
                    self.engine.add(app)
            elif self.status_cur == 'shutdown':
                return True
            elif self.status_cur == 'refresh':
                # jobqueues['hucore'].queue_details_hr()
                print jobqueues['hucore'].queue_details_hr()
                jsonf = os.path.join(self.dirs['status'], 'queue.json')
                jobqueues['hucore'].queue_details_json(jsonfile=jsonf)
                self.status_cur = self.status_pre
            elif self.status_cur == 'pause':
                # no need to do anything, just sleep and check requests again:
                pass
            time.sleep(1)


class HucoreDeconvolveApp(gc3libs.Application):

    """App object for 'hucore' deconvolution jobs.

    This application calls `hucore` with a given template file and retrives the
    stdout/stderr in a file named `stdout.txt` plus the directories `resultdir`
    and `previews` into a directory `deconvolved` inside the current directory.
    """

    def __init__(self, job, gc3_output):
        self.job = job   # remember the job object
        uid = self.job['uid']
        logw('Instantiating a HucoreDeconvolveApp:\n%s' % self.job)
        logi('Job UID: %s' % uid)
        # we need to add the template (with the local path) to the list of
        # files that need to be transferred to the system running hucore:
        self.job['infiles'].append(self.job['template'])
        # for the execution on the remote host, we need to strip all paths from
        # this string as the template file will end up in the temporary
        # processing directory together with all the images:
        templ_on_tgt = self.job['template'].split('/')[-1]
        gc3libs.Application.__init__(
            self,
            arguments = [self.job['exec'],
                '-exitOnDone',
                '-noExecLog',
                '-checkForUpdates', 'disable',
                '-template', templ_on_tgt],
            inputs = self.job['infiles'],
            outputs = ['resultdir', 'previews'],
            # collect the results in a subfolder of GC3Pie's spooldir:
            output_dir = os.path.join(gc3_output, 'results_%s' % uid),
            stderr = 'stdout.txt', # combine stdout & stderr
            stdout = 'stdout.txt')
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
            logc("Job '%s' terminated with unexpected EXIT CODE: %s!" %
                (self.job['uid'], self.execution.exitcode))
        else:
            logi("Job '%s' terminated successfully!" % self.job['uid'])
        logd("The output of the application is in `%s`." % self.output_dir)

    def has_finished(self):
        """Check the if the execution of the app has finished.

        Track and update the internal execution status of the app and print a
        log message if the status changes. Returns True if the app has
        terminated, False otherwise.
        """
        if not self.execution.state == self.laststate:
            logi("Job status changed to '%s'." % self.execution.state)
            self.laststate = self.execution.state
        if self.execution.state == gc3libs.Run.State.TERMINATED:
            return True
        else:
            return False


class HucorePreviewgenApp(gc3libs.Application):

    """App object for 'hucore' image preview generation jobs."""

    def __init__(self):
        # logw('Instantiating a HucorePreviewgenApp:\n%s' % job)
        logw('WARNING: this is a stub, nothing is implemented yet!')
        super(HucorePreviewgenApp, self).__init__()


class HucoreEstimateSNRApp(gc3libs.Application):

    """App object for 'hucore' SNR estimation jobs."""

    def __init__(self):
        # logw('Instantiating a HucoreEstimateSNRApp:\n%s' % job)
        logw('WARNING: this is a stub, nothing is implemented yet!')
        super(HucoreEstimateSNRApp, self).__init__()
