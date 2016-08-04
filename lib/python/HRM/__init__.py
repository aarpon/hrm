#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Support for various HRM related tasks.

Classes
-------

JobDescription()
    Parser for job descriptions, works on files or strings.

JobSpooler()
    Spooler processing the job files.
"""

# TODO: don't transfer the image files, create a symlink or put their path
#       into the HuCore Tcl script
# TODO: catch exceptions on dispatching jobs, otherwise the QM gets stuck:
#       it stops watching the "new" spool directory if instantiating a
#       gc3libs.Application fails (resulting in a "dead" state right now),
#       instead a notification needs to be sent/printed to the user (later
#       this should trigger an email).

import ConfigParser
import StringIO
import pprint
import time
import os
import shutil

from hashlib import sha1  # ignore this bug in pylint: disable=E0611

import gc3libs
from gc3libs.config import Configuration

from HRM.queue import JobQueue
from HRM.apps import hucore
from HRM.logger import *


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
        job.move_jobfile(dirs['done'])
        return
    job.move_jobfile(dirs['cur'])
    # TODO: have more than one queue, decide by 'tasktype' where to put a job
    try:
        queues[job['type']].append(job)
    except ValueError as err:
        loge("Adding the newe job from '%s' failed:\n    %s", fname, err)


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
            Can be either a filename pointing to a job config file, or a
            configuration itself, requires 'srctype' to be set accordingly!
        srctype : string
            One of ['file', 'string'], determines whether 'job' should be
            interpreted as a filename or as a job description string.

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
            job = self._read_jobfile()
        elif srctype == 'string':
            self.fname = "string"
        else:
            raise Exception("Unknown source type '%s'" % srctype)
        # store the SHA1 digest of this job, serving as the UID:
        self['uid'] = sha1(job).hexdigest()
        self.parse_jobconfig(job)
        # fill in keys without a reasonable value, they'll be updated later:
        self['status'] = "N/A"
        self['start'] = "N/A"
        self['progress'] = "N/A"
        self['pid'] = "N/A"
        self['server'] = "N/A"
        logi("Finished initialization of JobDescription().")
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
        # FIXME FIXME FIXME FIXME FIXME
        # this seems to be broken, moving unparsable jobfiles doesn't work!!!
        # FIXME FIXME FIXME FIXME FIXME
        # make sure to only move "file" job descriptions, return otherwise:
        if self.srctype != 'file':
            return
        target = os.path.join(target, self['uid'] + '.jobfile')
        move_file(self.fname, target)
        # update the job's internal fname pointer:
        self.fname = target

    def _read_jobfile(self):
        """Read in a job config file and pass it to the parser.

        Returns
        -------
        config_raw : str
            The file content as a single string.
        """
        logi("Parsing jobfile '%s'...", self.fname)
        if not os.path.exists(self.fname):
            raise IOError("Can't find file '%s'!" % self.fname)
        if not os.access(self.fname, os.R_OK):
            raise IOError("No permission reading file '%s'!" % self.fname)
        # sometimes the inotify event gets processed very rapidly and we're
        # trying to parse the file *BEFORE* it has been written to disk
        # entirely, which breaks the parsing, so we introduce four additional
        # levels of waiting time to avoid this race condition:
        config_raw = []
        for snooze in [0, 0.00001, 0.0001, 0.001, 0.01, 0.1]:
            if len(config_raw) == 0 and snooze > 0:
                logd("Jobfile could not be read, re-trying in %is.", snooze)
            time.sleep(snooze)
            with open(self.fname, 'r') as jobfile:
                config_raw = jobfile.read()
            if len(config_raw) > 0:
                logd("Job parsing succeeded after %s seconds!", snooze)
                break
        if len(config_raw) == 0:
            raise IOError("Unable to read job config file '%s'!" % self.fname)
        return config_raw

    def parse_jobconfig(self, cfg_raw):
        """Initialize ConfigParser and run parsing method."""
        try:
            self.jobparser.readfp(StringIO.StringIO(cfg_raw))
            logd("Parsed job configuration.")
        except ConfigParser.MissingSectionHeaderError as err:
            raise SyntaxError("ERROR in JobDescription: %s" % err)
        self._sections = self.jobparser.sections()
        if not self._sections:
            raise SyntaxError("No sections found in job config %s" % self.fname)
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
            # in this case we also adjust the UID of the job - this is mostly
            # done to allow submitting the same jobfile multiple times during
            # testing and should not be used in production, therefore we also
            # issue a corresponding warning message:
            self['uid'] = sha1(str(self['timestamp'])).hexdigest()
            logw('===%s', ' WARNING ===' * 8)
            logw('"timestamp = on_parsing" is meant for testing only!!!')
            logw('===%s', ' WARNING ===' * 8)
        else:
            # otherwise we need to convert to float, or raise an error:
            try:
                self['timestamp'] = float(self['timestamp'])
            except ValueError:
                raise ValueError("Invalid timestamp: %s." % self['timestamp'])
        ### now call the jobtype-specific parser method(s):
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
        if self['tasktype'] != 'decon' and self['tasktype'] != 'preview':
            raise ValueError("Tasktype invalid: %s" % self['tasktype'])
        # and the input file(s) section:
        # TODO: can we check if this section contains nonsense values?
        if 'inputfiles' not in self._sections:
            raise ValueError("Section 'inputfiles' missing in %s." % self.fname)
        self['infiles'] = []
        for option in self.jobparser.options('inputfiles'):
            infile = self._get_option('inputfiles', option)
            self['infiles'].append(infile)
        if not self['infiles']:
            raise ValueError("No input files defined in %s." % self.fname)

    def get_category(self):
        """Get the category of this job, in our case the value of 'user'."""
        return self['user']



class JobSpooler(object):

    """Spooler class processing the queue, dispatching jobs, etc.

    Instance Variables
    ------------------
    queue : HRM.JobQueue
    gc3spooldir : str
    gc3conf : str
    dirs : dict
    engine : gc3libs.core.Engine
    apps : list
    status : str
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
        self.apps = list()
        if not self.resource_dirs_clean():
            raise RuntimeError("GC3 resource dir unclean, refusing to start!")
        # the default status is 'run' unless explicitly requested (which will
        # be respected by the _spool() function anyway):
        self._status = self._status_pre = 'run'
        logi("Created JobSpooler.")

    @property
    def status(self):
        """Get the 'status' variable."""
        return self._status

    @status.setter
    def status(self, newstatus):
        """Set the 'status' variable, perform non-spooling actions."""
        if newstatus == 'refresh':
            # don't change the status on "refresh", instead simply print the
            # queue status and update the status file:
            logi("Received spooler queue status refresh request.")
            self.queue.queue_details_hr()
            logd(self.queue.queue_details_json())
            return
        if newstatus == self.status:
            # no change required, so return immediately:
            return
        self._status_pre = self.status
        self._status = newstatus
        logw("Received spooler status change request: %s -> %s",
             self._status_pre, self.status)

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
            logi("Using gc3pie spooldir: %s", self.gc3spooldir)
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
                self.status = fname
                # we don't process more than one request at a time, so exit:
                return

    def spool(self):
        """Wrapper method for the spooler to catch Ctrl-C."""
        try:
            self._spool()
        except KeyboardInterrupt:
            logi("Received keyboard interrupt, stopping queue manager.")
        self.cleanup()

    def _spool(self):
        """Spooler function dispatching jobs from the queues. BLOCKING!"""
        while True:
            self.check_status_request()
            if self.status == 'run':
                self.engine.progress()
                for i, app in enumerate(self.apps):
                    new_state = app.status_changed()
                    if new_state is not None:
                        self.queue.set_jobstatus(app.job, new_state)
                    if new_state == gc3libs.Run.State.TERMINATED:
                        app.job.move_jobfile(self.dirs['done'])
                        self.apps.pop(i)
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
                    app = hucore.HuDeconApp(nextjob, self.gc3spooldir)
                    self.apps.append(app)
                    self.engine.add(app)
                    # as a new job is dispatched now, we also print out the
                    # human readable queue status:
                    self.queue.queue_details_hr()
            elif self.status == 'shutdown':
                return True
            elif self.status == 'refresh':
                # the actual refresh action is handled by the status.setter
                # method, so we simply pass on:
                pass
            elif self.status == 'pause':
                # no need to do anything, just sleep and check requests again:
                pass
            time.sleep(0.5)

    def cleanup(self):
        """Clean up the spooler, terminate jobs, store status."""
        # TODO: store the current queue (see #516)
        logw("Queue Manager shutdown initiated.")
        logi("QM shutdown: cleaning up spooler.")
        if self.apps:
            logw("v%sv", "-" * 80)
            logw("Unfinished jobs, trying to stop them:")
            for app in self.apps:
                logw("Status of running job: %s", app.job['status'])
                self.kill_running_job(app)
            logw("^%s^", "-" * 80)
            self.engine.progress()
            stats = self._engine_status()
            if stats['RUNNING'] > 0:
                logc("Killing jobs failed, %s still running.", stats['RUNNING'])
            else:
                logi("Successfully terminated remaining jobs, none left.")
        logi("QM shutdown: spooler cleanup completed.")
        logw("QM shutdown: checking resource directories.")
        self.resource_dirs_clean()
        logw("QM shutdown: resource directories check completed.")

    def kill_running_job(self, app):
        """Helper method to kill a running job."""
        logw("<KILLING> [%s] %s", app.job['user'], type(app).__name__)
        app.kill()
        self.engine.progress()
        # TODO: clean up temporary gc3lib processing dir(s)
        #       app.kill() leaves the temporary gc3libs spooldir (files
        #       transferred for / generated from processing, logfiles
        #       etc.) alone, unfortunately the fetch_output() methods
        #       tested below do not work as suggested by the docs:
        ### self.engine.fetch_output(app)
        ### app.fetch_output()
        ### self.engine.progress()
        # remove the job from the queue:
        self.queue.remove(app.job['uid'])
        # trigger the update of the queue status json file:
        self.queue.queue_details_json()
        # this is just to trigger the stats messages in debug mode:
        self._engine_status()

    def _engine_status(self):
        """Helper to get the engine status and print a formatted log."""
        stats = self.engine.stats()
        logd("Engine: NEW:%s  SUBM:%s  RUN:%s  TERM'ing:%s  TERM'ed:%s  "
             "UNKNWN:%s  STOP:%s  (total:%s)",
             stats['NEW'], stats['SUBMITTED'], stats['RUNNING'],
             stats['TERMINATING'], stats['TERMINATED'], stats['UNKNOWN'],
             stats['STOPPED'], stats['total'])
        return stats
