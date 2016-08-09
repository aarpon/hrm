#!/usr/bin/env python
# -*- coding: utf-8 -*-#

"""
Prototype of a GC3Pie-based job spooler engine.
"""

# stdlib imports
import sys
import os
import argparse
import pyinotify

# pylint: disable=wrong-import-position
import HRM
import HRM.queue
import HRM.jobs
from HRM.logger import set_verbosity
from HRM.spooler import JobSpooler, setup_rundirs
from HRM.inotify import EventHandler


def parse_arguments():
    """Parse command line arguments."""
    argparser = argparse.ArgumentParser(description=__doc__)
    add_arg = argparser.add_argument  # just for readability of the next lines
    add_arg('-s', '--spooldir', required=True,
            help='spooling directory for jobfiles (e.g. "run/spool/")')
    add_arg('-c', '--config', required=False, default=None,
            help='GC3Pie config file (default: ~/.gc3/gc3pie.conf)')
    add_arg('-r', '--resource', required=False,
            help='GC3Pie resource name')
    add_arg('-v', '--verbosity', dest='verbosity',
            action='count', default=0)
    try:
        return argparser.parse_args()
    except IOError as err:
        argparser.error(str(err))


def main():
    """Main loop of the HRM Queue Manager."""
    args = parse_arguments()

    # set the loglevel as requested on the commandline
    set_verbosity(args.verbosity)

    spool_dirs = setup_rundirs(args.spooldir)
    jobqueues = dict()
    jobqueues['hucore'] = HRM.queue.JobQueue()
    for qname, queue in jobqueues.iteritems():
        status = os.path.join(spool_dirs['status'], qname + '.json')
        queue.set_statusfile(status)

    job_spooler = JobSpooler(spool_dirs, jobqueues['hucore'], args.config)


    # process jobfiles already existing during our startup:
    for jobfile in spool_dirs['newfiles']:
        fname = os.path.join(spool_dirs['new'], jobfile)
        HRM.jobs.process_jobfile(fname, jobqueues, spool_dirs)


    # select a specific resource if requested on the cmdline:
    if args.resource:
        job_spooler.select_resource(args.resource)

    watch_mgr = pyinotify.WatchManager()
    # set the mask which events to watch:
    mask = pyinotify.IN_CREATE                      # pylint: disable=E1101
    notifier = pyinotify.ThreadedNotifier(watch_mgr,
                                          EventHandler(queues=jobqueues,
                                                       dirs=spool_dirs))
    notifier.start()
    wdd = watch_mgr.add_watch(spool_dirs['new'], mask, rec=False)

    try:
        # NOTE: spool() is blocking, as it contains the main spooling loop!
        job_spooler.spool()
    finally:
        print 'Cleaning up. Remaining jobs:'
        print jobqueues['hucore'].queue
        watch_mgr.rm_watch(wdd.values())
        notifier.stop()

if __name__ == "__main__":
    sys.exit(main())
