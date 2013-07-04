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
import pprint

import logging
# loglevel = logging.DEBUG
loglevel = logging.WARN
gc3libs.configure_logger(loglevel, "qmgc3")
warn = gc3libs.log.warn

def parse_jobfile(fname):
    '''Parse details for an HRM job and check for sanity.
    .
    Take a job description file and assemble a dicitonary with the collected
    information that contains all the information for launching a new hucore
    processing task. Raises Exceptions in case something unexpected is found
    in the given file.
    '''
    # FIXME: currently only deconvolution jobs are supported!
    job = {}
    jobparser = ConfigParser.RawConfigParser()
    jobparser.read(jobfname)
    sections = jobparser.sections()
    # parse generic information, version, user etc.
    if not 'hrmjobfile' in sections:
        raise Exception("Error parsing job '%s'" % jobfname)
    try:
        job['ver'] = jobparser.get('hrmjobfile', 'version')
    except ConfigParser.NoOptionError:
        raise Exception("Can't find version in '%s'" % jobfname)
    # TODO: check the version number and parse accordingly...
    try:
        job['user'] = jobparser.get('hrmjobfile', 'username')
    except ConfigParser.NoOptionError:
        raise Exception("Can't find username in '%s'" % jobfname)
    # now parse the deconvolution section
    try:
        job['template'] = jobparser.get('deconvolution', 'template')
    except ConfigParser.NoOptionError:
        raise Exception("Can't find template in '%s'" % jobfname)

    # and the input file(s)
    section = 'inputfiles'
    if not section in sections:
        raise Exception("No input files defined in '%s'" % jobfname)
    job['infiles'] = []
    for option in jobparser.options('inputfiles'):
        infile = jobparser.get(section, option)
        job['infiles'].append(infile)
    return job

# TODO: use argparse for the jobfname
jobfname = 'spool/examples/deconvolution_job.cfg'
job = parse_jobfile(jobfname)


class HucoreDeconvolveApp(gc3libs.Application):
    """
    This application calls `hucore` with a given template file and retrives the
    stdout/stderr in a file named `stdout.txt` plus the directories `resultdir`
    and `previews` into a directory `deconvolved` inside the current directory.
    """
    # TODO: path to hucore should not be hardcoded here
    def __init__(self, job):
        warn("Job settings:\n%s" % pprint.pformat(job))
        job['infiles'].append(job['template'])
        gc3libs.Application.__init__(
            self,
            arguments = ["/usr/local/bin/hucore",
                '-template', job['template']],
            inputs = job['infiles'],
            outputs = ['resultdir', 'previews'],
            output_dir = './deconvolved',
            stderr = 'stdout.txt', # combine stdout & stderr
            stdout = 'stdout.txt')

warn('Creating an instance of HucoreDeconvolveApp using the parsed job.')
app = HucoreDeconvolveApp(job)

warn('Creating an instance of a GC3Pie engine using the configuration '
    'file present in your home directory.')
engine = gc3libs.create_engine()

# Add your application to the engine. This will NOT submit your application
# yet, but will make the engine *aware* of the application.
engine.add(app)

# TODO: use argparse to have a sophisticated and sane way for this:
# in case you want to select a specific resource, call
# `Engine.select_resource(<resource_name>)`
if len(sys.argv)>1:
    engine.select_resource(sys.argv[1])

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
