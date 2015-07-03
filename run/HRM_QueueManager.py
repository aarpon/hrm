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
# - if instantiating a gc3libs.Application fails, the QM stops watching and
#   parsing new job files (resulting in a "dead" state right now), so
#   exceptions on dispatching jobs need to be caught and some notification
#   needs to be sent/printed to the user (later this should trigger an email).
# - move jobfiles of terminated jobs to 'done'
# - let gc3pie decide when to dispatch a job (currently the call to run_job()
#   is blocking and thus the whole thing is limited to single sequential job
#   instances, even if more resources were available

# stdlib imports
import sys
import time
import os
import shutil

# GC3Pie imports
try:
    import gc3libs
except ImportError:
    print("ERROR: unable to import GC3Pie library package, please make sure")
    print("it is installed and active, e.g. by running this command prior to")
    print("starting the HRM Queue Manager:")
    print("\n$ source /path/to/your/gc3pie_installation/bin/activate\n")
    sys.exit(1)

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
loge = gc3libs.log.error
logc = gc3libs.log.critical


# this is read from the gc3pie config file for now, see below!
GC3_SPOOLDIR = ''


class EventHandler(pyinotify.ProcessEvent):

    """Handler for pyinotify filesystem events.

    An instance of this class can be registered as a handler to pyinotify and
    then gets called to process an event registered by pyinotify.

    Public Methods
    --------------
    process_IN_CREATE()
    """

    def my_init(self, queues=dict(), parsed_jobs=None):
        """Initialize the inotify event handler.

        Parameters
        ----------
        queues : dict
            Containing the JobQueue objects for the different queues, using the
            corresponding 'type' keyword as identifier.
        parsed_jobs : str
            The path to a directory where to move parsed jobfiles.
        """
        logi("Initialized the event handler for inotify.")
        # TODO: we need to distinguish different job types and act accordingly
        self.queues = queues
        self.parsed_jobs = parsed_jobs

    def process_IN_CREATE(self, event):
        """Method handling 'create' events."""
        logw("Found new jobfile '%s', processing..." % event.pathname)
        try:
            job = HRM.JobDescription(event.pathname, 'file', loglevel)
            logd("Dict assembled from the processed job file:")
            logd(pprint.pformat(job))
        except IOError as err:
            logw("Error parsing job description file: %s" % err)
            # in this case there is nothing to add to the queue, so we simply
            # return silently
            return
        self.queues[job['type']].append(job)
        self.move_jobfiles(event.pathname, job)
        logd("Current job queue for type '%s': %s" %
                (job['type'], self.queues[job['type']].queue))

    def move_jobfiles(self, jobfile, job):
        """Move a parsed jobfile to the corresponding spooling subdir."""
        target = os.path.join(self.parsed_jobs, job['uid'] + '.cfg')
        logd("Moving jobfile '%s' to '%s'." % (jobfile, target))
        shutil.move(jobfile, target)


def parse_arguments():
    """Parse command line arguments."""
    argparser = argparse.ArgumentParser(description=__doc__)
    argparser.add_argument('-s', '--spooldir', required=True,
        help='spooling directory for new jobfiles')
    argparser.add_argument('-c', '--config', required=False, default=None,
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
    global GC3_SPOOLDIR
    args = parse_arguments()

    # set the loglevel as requested on the commandline
    loglevel = logging.WARN - (args.verbosity * 10)
    gc3libs.configure_logger(loglevel, "qmgc3")

    job_spooler = HRM.JobSpooler(args.spooldir, args.config)

    jobqueues = dict()
    jobqueues['hucore'] = HRM.JobQueue()

    # select a specific resource if requested on the cmdline:
    if args.resource:
        job_spooler.select_resource(args.resource)

    wm = pyinotify.WatchManager()  # watch manager
    mask = pyinotify.IN_CREATE     # watched events
    notifier = pyinotify.ThreadedNotifier(wm,
        EventHandler(queues=jobqueues, parsed_jobs=job_spooler.dirs['cur']))
    notifier.start()
    wdd = wm.add_watch(job_spooler.dirs['new'], mask, rec=False)

    print('HRM Queue Manager started, watching spool directory "%s", '
          'press Ctrl-C to abort.' % job_spooler.dirs['new'])
    logi('Excpected job description files version: %s.' % HRM.JOBFILE_VER)

    try:
        # NOTE: spool() is blocking, as it contains the main spooling loop!
        job_spooler.spool(jobqueues)
    finally:
        print('Cleaning up. Remaining jobs:')
        # TODO: before exiting with a non-empty queue, it should be serialized
        # and stored in a file (e.g. using the "pickle" module)
        print(jobqueues['hucore'].queue)
        wm.rm_watch(wdd.values())
        notifier.stop()

if __name__ == "__main__":
    sys.exit(main())
