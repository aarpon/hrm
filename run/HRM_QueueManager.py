#!/usr/bin/env python
# -*- coding: utf-8 -*-#
# @(#)HRM_QueueManager.py
#
"""
The prototype of a new GC3Pie-based Queue Manager for HRM.
"""

# TODO:
# - monitor a "new" directory via pyinotify
# - add them to the queue

# stdlib imports
import sys
import time

# GC3Pie imports
import gc3libs

import ConfigParser
import argparse
import pprint

import logging
# loglevel = logging.DEBUG
loglevel = logging.WARN
gc3libs.configure_logger(loglevel, "qmgc3")
warn = gc3libs.log.warn

def parse_job_hucore(jobparser, sections, job):
    '''Does the specific parsing of "hucore" type jobfiles.
    .
    Parses the "hucore" and the "inputfiles" sections of HRM job configuration
    files.
    .
    Returns
    -------
    void
        Adds all information directly to the "job" dict.
    '''
    # the "hucore" section:
    try:
        job['exec'] = jobparser.get('hucore', 'executable')
    except ConfigParser.NoOptionError:
        raise Exception("Can't find executable in '%s'" % jobfname)
    try:
        job['template'] = jobparser.get('hucore', 'template')
    except ConfigParser.NoOptionError:
        raise Exception("Can't find template in '%s'" % jobfname)
    # and the input file(s):
    if not 'inputfiles' in sections:
        raise Exception("No input files defined in '%s'" % jobfname)
    job['infiles'] = []
    for option in jobparser.options('inputfiles'):
        infile = jobparser.get('inputfiles', option)
        job['infiles'].append(infile)


def parse_jobfile(name):
    '''Parse details for an HRM job and check for sanity.
    .
    Take a job description file and assemble a dicitonary with the collected
    information that contains all the information for launching a new hucore
    processing task. Raises Exceptions in case something unexpected is found
    in the given file.
    '''
    # FIXME: currently only deconvolution jobs are supported, until hucore will
    # be able to do the other things like SNR estimation and previewgen using
    # templates as well!
    job = {}
    jobparser = ConfigParser.RawConfigParser()
    jobparser.read(name)
    sections = jobparser.sections()
    # parse generic information, version, user etc.
    if not 'hrmjobfile' in sections:
        raise Exception("Can't find expected header in '%s'" % name)
    try:
        job['ver'] = jobparser.get('hrmjobfile', 'version')
    except ConfigParser.NoOptionError:
        raise Exception("Can't find version in '%s'" % name)
    if not (job['ver'] == '2'):
        raise Exception("Unexpected jobfile version '%s'" % job['ver'])
    try:
        job['user'] = jobparser.get('hrmjobfile', 'username')
    except ConfigParser.NoOptionError:
        raise Exception("Can't find username in '%s'" % name)
    try:
        job['type'] = jobparser.get('hrmjobfile', 'jobtype')
    except ConfigParser.NoOptionError:
        raise Exception("Can't find jobtype in '%s'" % name)

    # from here on a jobtype specific parsing must be done:
    if job['type'] == 'hucore':
        parse_job_hucore(jobparser, sections, job)
    else:
        raise Exception("Unknown jobtype '%s'" % job['type'])
    return job


class HucoreDeconvolveApp(gc3libs.Application):
    """
    This application calls `hucore` with a given template file and retrives the
    stdout/stderr in a file named `stdout.txt` plus the directories `resultdir`
    and `previews` into a directory `deconvolved` inside the current directory.
    """
    def __init__(self, job):
        warn("Job settings:\n%s" % pprint.pformat(job))
        # we need to add the template (with the local path) to the list of
        # files that need to be transferred to the system running hucore:
        job['infiles'].append(job['template'])
        # for the execution on the remote host, we need to strip all paths from
        # this string as the template file will end up in the temporary
        # processing directory together with all the images:
        templ_on_tgt = job['template'].split('/')[-1]
        gc3libs.Application.__init__(
            self,
            arguments = [job['exec'], '-template', templ_on_tgt],
            inputs = job['infiles'],
            outputs = ['resultdir', 'previews'],
            output_dir = './deconvolved',
            stderr = 'stdout.txt', # combine stdout & stderr
            stdout = 'stdout.txt')


def main():
    argparser = argparse.ArgumentParser(description=__doc__)
    argparser.add_argument('-j', '--jobfile', required=True,
        help='job description file in ConfigParser format')
    argparser.add_argument('-r', '--resource', required=False,
        help='GC3Pie resource name')
    try:
        args = argparser.parse_args()
    except IOError as e:
        argparser.error(str(e))

    job = parse_jobfile(args.jobfile)

    warn('Creating an instance of HucoreDeconvolveApp using the parsed job.')
    app = HucoreDeconvolveApp(job)

    warn('Creating an instance of a GC3Pie engine using the configuration '
        'file present in your home directory.')
    engine = gc3libs.create_engine()

    # Add your application to the engine. This will NOT submit your application
    # yet, but will make the engine *aware* of the application.
    engine.add(app)

    # select a specific resource if requested on the cmdline:
    if args.resource:
        engine.select_resource(args.resource)

    # Periodically check the status of your application.
    while app.execution.state != gc3libs.Run.State.TERMINATED:
        print "Job in status %s " % app.execution.state
        # `Engine.progress()` will do the GC3Pie magic: submit new jobs, update
        # status of submitted jobs, get results of terminating jobs etc...
        engine.progress()

        # Wait a few seconds...
        time.sleep(1)

    print "Job is now terminated."
    print "The output of the application is in `%s`." %  app.output_dir

if __name__ == "__main__":
    sys.exit(main())
