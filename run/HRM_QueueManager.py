#!/usr/bin/env python
# -*- coding: utf-8 -*-#
# @(#)HRM_QueueManager.py
#
"""
The prototype of a new GC3Pie-based Queue Manager for HRM.
"""

# TODO:
# - do not transfer the images, create a symlink or put their path into the
#   HuCore Tcl script
# - put the results dir back to the user's destination directory
# - check if a sane (usable) gc3pie configuration exists!
# - if instantiating a gc3libs.Application fails, the QM stops watching and
#   parsing new job files (resulting in a "dead" state right now), so
#   exceptions on dispatching jobs need to be caught and some notification
#   needs to be sent/printed to the user (later this should trigger an email).
# - move processed jobfiles to cur/done
# - let gc3pie decide when to dispatch a job (currently the call to run_job()
#   is blocking and thus the whole thing is limited to single sequential job
#   instances, even if more resources were available

# stdlib imports
import sys
import time
import os

# GC3Pie imports
import gc3libs

import pyinotify
import argparse
import pprint

import HRM

import logging
# we set a default loglevel and add some shortcuts for logging:
loglevel = logging.WARN
gc3libs.configure_logger(loglevel, "qmgc3")
logw = gc3libs.log.warn
logi = gc3libs.log.info
logd = gc3libs.log.debug


# TODO: this has to be set+read in a config file eventually:
GC3_SPOOLDIR = '/scratch/hrm_data'


class EventHandler(pyinotify.ProcessEvent):

    """Handler for pyinotify filesystem events.

    An instance of this class can be registered as a handler to pyinotify and
    then gets called to process an event registered by pyinotify.

    Public Methods
    --------------
    process_IN_CREATE()
    """

    def my_init(self, queues=dict()):
        """Initialize the inotify event handler.

        Parameters
        ----------
        queues : dict
            Containing the JobQueue objects for the different queues, using the
            corresponding 'type' keyword as identifier.
        """
        logi("Initialized the event handler for inotify.")
        # TODO: we need to distinguish different job types and act accordingly
        # FIXME: does it work setting an instance variable like this?!
        self.queues = queues

    def process_IN_CREATE(self, event):
        """Method handling 'create' events."""
        logw("Found new jobfile '%s', processing..." % event.pathname)
        try:
            job = HRM.JobDescription(event.pathname, 'file', loglevel)
            logi("Dict assembled from the processed job file:")
            logi(pprint.pformat(job))
        except IOError as e:
            logw("Error parsing job description file: %s" % e)
            # in this case there is nothing to add to the queue, so we simply
            # return silently
            return
        self.queues[job['type']].append(job)
        logd("Current job queue for type '%s': %s" %
                (job['type'], self.queues[job['type']].queue))


class HucoreDeconvolveApp(gc3libs.Application):

    """App object for 'hucore' deconvolution jobs.

    This application calls `hucore` with a given template file and retrives the
    stdout/stderr in a file named `stdout.txt` plus the directories `resultdir`
    and `previews` into a directory `deconvolved` inside the current directory.
    """

    def __init__(self, job):
        logw('Instantiating a HucoreDeconvolveApp:\n%s' % job)
        logi('Job UID: %s' % job['uid'])
        # we need to add the template (with the local path) to the list of
        # files that need to be transferred to the system running hucore:
        job['infiles'].append(job['template'])
        # for the execution on the remote host, we need to strip all paths from
        # this string as the template file will end up in the temporary
        # processing directory together with all the images:
        templ_on_tgt = job['template'].split('/')[-1]
        gc3libs.Application.__init__(
            self,
            arguments = [job['exec'],
                '-exitOnDone',
                '-noExecLog',
                '-checkForUpdates', 'disable',
                '-template', templ_on_tgt],
            inputs = job['infiles'],
            outputs = ['resultdir', 'previews'],
            # collect the results in a subfolder of GC3Pie's spooldir:
            output_dir = os.path.join(GC3_SPOOLDIR, 'results_%s' % job['uid']),
            stderr = 'stdout.txt', # combine stdout & stderr
            stdout = 'stdout.txt')


class HucorePreviewgenApp(gc3libs.Application):

    """App object for 'hucore' image preview generation jobs."""

    # stub!
    pass


class HucoreEstimateSNRApp(gc3libs.Application):

    """App object for 'hucore' SNR estimation jobs."""

    # stub!
    pass


def run_job(engine, job):
    """Run a job in a singlethreaded and blocking manner via GC3Pie.

    NOTE: this doesn't mean the process executed during this job is
    singlethreaded, it just means that currently no more than one job is run
    *at a time*.
    """
    app = HucoreDeconvolveApp(job)

    # Add your application to the engine. This will NOT submit your application
    # yet, but will make the engine *aware* of the application.
    engine.add(app)

    # Periodically check the status of your application.
    laststate = app.execution.state
    curstate = app.execution.state
    while laststate != gc3libs.Run.State.TERMINATED:
        # `Engine.progress()` will do the GC3Pie magic: submit new jobs, update
        # status of submitted jobs, get results of terminating jobs etc...
        engine.progress()
        curstate = app.execution.state
        if not (curstate == laststate):
            logw("Job in status %s " % curstate)

        laststate = app.execution.state
        # Wait a few seconds...
        time.sleep(1)
    logw("Job is now terminated.")
    logw("The output of the application is in `%s`." %  app.output_dir)


def resource_dirs_clean(engine):
    """Check if the resource dirs of all resources are clean.

    Parameters
    ----------
    engine : gc3libs.core.Engine
        The GC3 engine to check the resource directories for.

    Returns
    -------
    bool
    """
    # NOTE: with the session-based GC3 approach, it should be possible to pick
    # up existing (leftover) jobs in a resource directory upon start and figure
    # out what their status is, clean up, collect results etc.
    for resource in engine.get_resources():
        resourcedir = os.path.expandvars(resource.cfg_resourcedir)
        print("Checking resource dir for resource '%s': %s" %
            (resource.name, resourcedir))
        if not os.path.exists(resourcedir):
            continue
        files = os.listdir(resourcedir)
        if files:
            print("Resource dir unclean: %s" % files)
            return False
    return True


def parse_arguments():
    """Parse command line arguments."""
    argparser = argparse.ArgumentParser(description=__doc__)
    argparser.add_argument('-s', '--spooldir', required=True,
        help='spooling directory for new jobfiles')
    argparser.add_argument('-c', '--config', required=False,
        help='GC3Pie config file (default: ~/.gc3/gc3pie.conf)')
    argparser.add_argument('-r', '--resource', required=False,
        help='GC3Pie resource name')
    # TODO: use HRM.queue_details_hr() for generating the queuelist:
    argparser.add_argument('-q', '--queuelist', required=False,
        help='file to write the current queuelist to (default: stdout)')
    argparser.add_argument('-v', '--verbosity', dest='verbosity',
        action='count', default=0)
    try:
        return argparser.parse_args()
    except IOError as err:
        argparser.error(str(err))


def main():
    """Main loop of the HRM Queue Manager."""
    args = parse_arguments()

    if not os.path.exists(args.spooldir):
        raise IOError("Spool directory doesn't exist: '%s'." % args.spooldir)

    # set the loglevel as requested on the commandline
    loglevel = logging.WARN - (args.verbosity * 10)
    gc3libs.configure_logger(loglevel, "qmgc3")

    jobqueues = dict()
    jobqueues['hucore'] = HRM.JobQueue()

    wm = pyinotify.WatchManager() # Watch Manager
    mask = pyinotify.IN_CREATE # watched events
    notifier = pyinotify.ThreadedNotifier(wm, EventHandler(queues=jobqueues))
    notifier.start()
    wdd = wm.add_watch(args.spooldir, mask, rec=False)

    # If create_engine() is called without arguments, it will use the default
    # config file in ~/.gc3/gc3pie.conf (see the gc3libs API for details).
    if args.config:
        logi('Creating GC3Pie engine using config file "%s".' % args.config)
        engine = gc3libs.create_engine(args.config)
    else:
        logi('Creating GC3Pie engine with the default config file.')
        engine = gc3libs.create_engine()
    # select a specific resource if requested on the cmdline:
    if args.resource:
        engine.select_resource(args.resource)

    if not resource_dirs_clean(engine):
        print("Refusing to start, clean your resource dir first!")
        wm.rm_watch(wdd.values())
        notifier.stop()
        return 2

    logi('Excpected job description files version: %s.' % HRM.JOBFILE_VER)
    print('HRM Queue Manager started, watching spool directory "%s", '
          'press Ctrl-C to abort.' % args.spooldir)
    # FIXME: Ctrl-C while a job is running leaves it alone (and thus as well
    # the files transferred for / generated from processing)
    while True:
        try:
            nextjob = jobqueues['hucore'].pop()
            if nextjob is not None:
                logd("Current joblist: %s" % jobqueues['hucore'].queue)
                logd("Dispatching next job.")
                run_job(engine, nextjob)
            time.sleep(1)
        except KeyboardInterrupt:
            break

    print('Cleaning up. Remaining jobs:')
    # TODO: before exiting with a non-empty queue, it should be serialized and
    # stored in a file (e.g. using the "pickle" module)
    print(jobqueues['hucore'].queue)
    wm.rm_watch(wdd.values())
    notifier.stop()

if __name__ == "__main__":
    sys.exit(main())
