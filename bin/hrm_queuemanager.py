#!/usr/bin/env python
# -*- coding: utf-8 -*-#
# @(#)HRM_QueueManager.py
#
"""
The prototype of a new GC3Pie-based Queue Manager for HRM.
"""

# stdlib imports
import sys
import os

TOP = os.path.abspath(os.path.dirname(sys.argv[0]) + '/../')
LPY = os.path.join(TOP, "lib", "python")
sys.path.insert(0, LPY)

import pyinotify
import argparse
import pprint


# GC3Pie imports
try:
    import gc3libs
except ImportError:
    print "=" * 80
    print """
    ERROR: unable to import GC3Pie library package, please make sure it is
    installed and active, e.g. by running this command before starting the HRM
    Queue Manager:\n
    $ source /path/to/your/gc3pie_installation/bin/activate
    """
    print "=" * 80
    sys.exit(1)

import HRM

import logging
# we set a default loglevel and add some shortcuts for logging:
LOGLEVEL = logging.WARN
gc3libs.configure_logger(LOGLEVEL, "qmgc3")
logw = gc3libs.log.warn
logi = gc3libs.log.info
logd = gc3libs.log.debug
loge = gc3libs.log.error
logc = gc3libs.log.critical


class EventHandler(pyinotify.ProcessEvent):

    """Handler for pyinotify filesystem events.

    An instance of this class can be registered as a handler to pyinotify and
    then gets called to process an event registered by pyinotify.

    Public Methods
    --------------
    process_IN_CREATE()
    """

    def my_init(self, queues, tgt):                 # pylint: disable-msg=W0221
        """Initialize the inotify event handler.

        Parameters
        ----------
        queues : dict
            Containing the JobQueue objects for the different queues, using the
            corresponding 'type' keyword as identifier.
        tgt : dict
            Spooling directories in a dict, as returned by HRM.setup_rundirs().
        """
        logi("Initialized the event handler for inotify.")
        self.queues = queues
        self.tgt = tgt

    def process_IN_CREATE(self, event):
        """Method handling 'create' events."""
        logw("Found new jobfile '%s', processing..." % event.pathname)
        try:
            # TODO: use better approach for the LOGLEVEL here:
            job = HRM.JobDescription(event.pathname, 'file', LOGLEVEL)
            logd("Dict assembled from the processed job file:")
            logd(pprint.pformat(job))
        except IOError as err:
            logw("Error reading job description file (%s), skipping." % err)
            # there is nothing to add to the queue and the IOError indicates
            # problems accessing the file, so we simply return silently:
            return
        except (SyntaxError, ValueError) as err:
            logw("Job file unparsable (%s), skipping / moving to 'done'." % err)
            # still nothing to add to the queue but this time we can at least
            # move the file out of the way before returning:
            HRM.move_file(event.pathname, self.tgt['done'], safe=True)
            return
        if not self.queues.has_key(job['type']):
            logc("ERROR: no queue existing for jobtype '%s'!" % job['type'])
            HRM.move_file(event.pathname, self.tgt['done'], safe=True)
            return
        # TODO: we need to distinguish different job types and act accordingly
        self.queues[job['type']].append(job)
        job.move_jobfile(self.tgt['cur'])
        logd("Current job queue for type '%s': %s" %
                (job['type'], self.queues[job['type']].queue))


def parse_arguments():
    """Parse command line arguments."""
    argparser = argparse.ArgumentParser(description=__doc__)
    argparser.add_argument('-s', '--spooldir', required=True,
        help='spooling directory for jobfiles (e.g. "run/spool/")')
    argparser.add_argument('-c', '--config', required=False, default=None,
        help='GC3Pie config file (default: ~/.gc3/gc3pie.conf)')
    argparser.add_argument('-r', '--resource', required=False,
        help='GC3Pie resource name')
    argparser.add_argument('-v', '--verbosity', dest='verbosity',
        action='count', default=0)
    try:
        return argparser.parse_args()
    except IOError as err:
        argparser.error(str(err))


def main():
    """Main loop of the HRM Queue Manager."""
    args = parse_arguments()

    # set the loglevel as requested on the commandline
    loglevel = logging.WARN - (args.verbosity * 10)
    gc3libs.configure_logger(loglevel, "qmgc3")

    spool_dirs = HRM.setup_rundirs(args.spooldir)
    job_spooler = HRM.JobSpooler(spool_dirs, args.config)

    jobqueues = dict()
    jobqueues['hucore'] = HRM.JobQueue()
    for qname, queue in jobqueues.iteritems():
        status = os.path.join(job_spooler.dirs['status'], qname + '.json')
        queue.set_statusfile(status)


    # select a specific resource if requested on the cmdline:
    if args.resource:
        job_spooler.select_resource(args.resource)

    watch_mgr = pyinotify.WatchManager()
    # set the mask which events to watch:
    mask = pyinotify.IN_CREATE                      # pylint: disable-msg=E1101
    notifier = pyinotify.ThreadedNotifier(watch_mgr,
        EventHandler(queues=jobqueues, tgt=spool_dirs))
    notifier.start()
    wdd = watch_mgr.add_watch(job_spooler.dirs['new'], mask, rec=False)

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
        watch_mgr.rm_watch(wdd.values())
        notifier.stop()

if __name__ == "__main__":
    sys.exit(main())
