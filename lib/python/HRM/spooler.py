# -*- coding: utf-8 -*-
"""
Job spooler class module.

Classes
-------

JobSpooler()
    Spooler processing jobs.
"""

# TODO: don't transfer the image files, create a symlink or put their path
#       into the HuCore Tcl script
# TODO: catch exceptions on dispatching jobs, otherwise the QM gets stuck:
#       it stops watching the "new" spool directory if instantiating a
#       gc3libs.Application fails (resulting in a "dead" state right now),
#       instead a notification needs to be sent/printed to the user (later
#       this should trigger an email).

import os
import pprint
import time

from . import logi, logd, logw, logc, loge, JOBFILE_VER
from .apps import hucore

import gc3libs
import gc3libs.config


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
        'spool': ['new', 'cur', 'done'],
        'queue': ['status', 'requests']
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


class JobSpooler(object):
    """Spooler class processing the queue, dispatching jobs, etc.

    Instance Variables
    ------------------
    queue : HRM.JobQueue
    # queues : dict(HRM.JobQueue)  # TODO: multi-queue logic (#136, #272)
    gc3spooldir : str
    gc3conf : str
    dirs : dict
    engine : gc3libs.core.Engine
    apps : list
    status : str
    """

    def __init__(self, spool_dirs, queue, gc3conf):
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
        # self.queues = dict()  # TODO: multi-queue logic (#136, #272)
        self.gc3cfg = self._check_gc3conf(gc3conf)
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

    def _check_gc3conf(self, gc3conffile):
        """Check the gc3 config file and extract the gc3 spooldir.

        Parameters
        ----------
        gc3conffile : str

        Returns
        -------
        cfg : dict
            A dict with keys 'spooldir' and 'conffile'.
        """
        cfg = dict()
        gc3conf = gc3libs.config.Configuration(gc3conffile)
        try:
            cfg['spooldir'] = gc3conf.resources['localhost'].spooldir
            logi("Using gc3pie spooldir: %s", cfg['spooldir'])
        except AttributeError:
            raise AttributeError("Unable to parse spooldir for resource "
                                 "'localhost' from gc3pie config file '%s'!" %
                                 gc3conffile)
        cfg['conffile'] = gc3conffile
        return cfg

    def setup_engine(self):
        """Wrapper to set up the GC3Pie engine.

        Returns
        -------
        gc3libs.core.Engine
        """
        logi('Creating GC3Pie engine using config file "%s".',
             self.gc3cfg['conffile'])
        return gc3libs.create_engine(self.gc3cfg['conffile'])

    def select_resource(self, resource):
        """Select a specific resource for the GC3Pie engine."""
        self.engine.select_resource(resource)

    def resource_dirs_clean(self):
        """Check if the resource dirs of all resources are clean.

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

    def check_for_jobs_to_delete(self):
        """Process job deletion requests for all queues."""
        # first process jobs that have been dispatched already:
        for app in self.apps:
            uid = app.job['uid']
            if uid in self.queue.deletion_list:
                # TODO: we need to make sure that the calls to the engine in
                # kill_running_job() do not accidentially submit the next job
                # as it could be potentially enlisted for removal...
                self.kill_running_job(app)
                self.queue.deletion_list.remove(uid)
        # then process deletion requests for waiting jobs (note: killed jobs
        # have been removed from the queue by the kill_running_job() method)
        self.queue.process_deletion_list()

    def spool(self):
        """Wrapper method for the spooler to catch Ctrl-C."""
        try:
            self._spool()
        except KeyboardInterrupt:
            logi("Received keyboard interrupt, stopping queue manager.")
        self.cleanup()

    def _spool(self):
        """Spooler function dispatching jobs from the queues. BLOCKING!"""
        print '*' * 80
        print 'HRM spooler running. (Ctrl-C to abort).'
        print '*' * 80
        logi('Excpected jobfile version: %s.', JOBFILE_VER)
        while True:
            self.check_status_request()
            if self.status == 'run':
                # process deletion requests before anything else
                self.check_for_jobs_to_delete()
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
                    app = hucore.HuDeconApp(nextjob, self.gc3cfg['spooldir'])
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
        state = app.status_changed()
        if state != 'TERMINATED':
            loge("Expected status 'TERMINATED', found '%s'!", state)
        else:
            logw("App has terminated, removing from list of apps.")
            self.apps.remove(app)
        # TODO: clean up temporary gc3lib processing dir(s)
        #       app.kill() leaves the temporary gc3libs spooldir (files
        #       transferred for / generated from processing, logfiles
        #       etc.) alone, unfortunately the fetch_output() methods
        #       tested below do not work as suggested by the docs:
        # ## self.engine.fetch_output(app)
        # ## app.fetch_output()
        # ## self.engine.progress()
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
