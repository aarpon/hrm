#!/usr/bin/env python
# -*- coding: utf-8 -*-#
# @(#)HRM_QueueManager.py
#
"""
The prototype of a new GC3Pie-based Queue Manager for HRM.
"""

# TODO:
# - check if a sane (usable) gc3pie configuration exists!
# - check if the gc3pie resource folder is clean on startup (for now this
#   is ~/.gc3/shellcmd.d but this will probably change when switching to the
#   session based variant of GC3Pie
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


class EventHandler(pyinotify.ProcessEvent):

    """Handler for pyinotify filesystem events.

    An instance of this class can be registered as a handler to pyinotify and
    then gets called to process an event registered by pyinotify.

    Public Methods
    --------------
    process_IN_CREATE()
    """

    def __init__(self, queues):
        """Initialize the inotify event handler.

        Parameters
        ----------
        queues : dict
            Containing the JobQueue objects for the different queues, using the
            corresponding 'type' keyword as identifier.
        """
        logi("Initialized the event handler for inotify.")
        # TODO: we need to distinguish different job types and act accordingly
        self.queues = queues

    def process_IN_CREATE(self, event):
        """Method handling 'create' events."""
        logw("Found new jobfile '%s', processing..." % event.pathname)
        job = HRM.JobDescription(event.pathname, 'file', loglevel)
        logi("Dict assembled from the processed job file:")
        logi(pprint.pformat(job))
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
        # we need to add the template (with the local path) to the list of
        # files that need to be transferred to the system running hucore:
        job['infiles'].append(job['template'])
        # for the execution on the remote host, we need to strip all paths from
        # this string as the template file will end up in the temporary
        # processing directory together with all the images:
        templ_on_tgt = job['template'].split('/')[-1]
        gc3libs.Application.__init__(
            self,
            arguments = [job['exec'], '-exitOnDone -noExecLog -checkForUpdates disable -template', templ_on_tgt],
            inputs = job['infiles'],
            outputs = ['resultdir', 'previews'],
            output_dir = './deconvolved',
            stderr = 'stdout.txt', # combine stdout & stderr
            stdout = 'stdout.txt')


class HucorePreviewgenApp(gc3libs.Application):

    """App object for 'hucore' image preview generation jobs."""

    # stub!
    pass


class HucorePreviewgenApp(gc3libs.Application):

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


def main():
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
        args = argparser.parse_args()
    except IOError as e:
        argparser.error(str(e))

    # set the loglevel as requested on the commandline
    loglevel = logging.WARN - (args.verbosity * 10)
    gc3libs.configure_logger(loglevel, "qmgc3")

    jobqueues = dict()
    jobqueues['hucore'] = HRM.JobQueue()

    wm = pyinotify.WatchManager() # Watch Manager
    mask = pyinotify.IN_CREATE # watched events
    notifier = pyinotify.ThreadedNotifier(wm, EventHandler(jobqueues))
    notifier.start()
    watchdir = args.spooldir
    wdd = wm.add_watch(watchdir, mask, rec=False)

    # If no configuration file is passed to create_engine(), it will take the
    # default config which is expected to be in ~/.gc3/gc3pie.conf. See the API
    # documentation for more details about this:
    # http://gc3pie.readthedocs.org/en/latest/programmers/api/gc3libs.html
    if args.config:
        logi('Creating an instance of a GC3Pie engine using the configuration '
             'file "%s".' % args.config)
        engine = gc3libs.create_engine(args.config)
    else:
        logi('Creating an instance of a GC3Pie engine using the configuration '
             'file present in your home directory.')
        engine = gc3libs.create_engine()
    # select a specific resource if requested on the cmdline:
    if args.resource:
        engine.select_resource(args.resource)

    logi('Excpected job description files version: %s.' % HRM.JOBFILE_VER)
    print('HRM Queue Manager started, watching spool directory "%s", '
          'press Ctrl-C to abort.' % watchdir)
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
