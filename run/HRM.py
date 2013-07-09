#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Support for various HRM related tasks.

Classes
-------

JobDescription()
    Parser for job descriptions, works on files or strings.
"""

import ConfigParser

__all__ = ['JobDescription']


class JobDescription(object):
    """
    Abstraction class for handling HRM job descriptions.

    Read an HRM job description either from a file or a string and parse
    the sections, check them for sane values and store them in a dict.
    """

    def __init__(self, job, method):
        """Check method ('string' or 'file') and initalize accordingly."""
        self.job = {}
        self.jobparser = ConfigParser.RawConfigParser()
        self._sections = []
        if (method == 'file'):
            self.name = "file '%s'" % job
            self._parse_jobfile(job)
        else:
            # TODO: _parse_jobstring(job)
            self.name = "string received from socket"
            raise Exception("Method 'string' not yet implemented!")

    def __getitem__(self, key):
        return self.job[key]

    def __setitem__(self, key, value):
        self.job[key] = value

    def __repr__(self):
        # TODO: figure out why pprint.pformat() behaves different on repr()
        # compared to pformat({})
        return repr(self.job)

    def _parse_jobfile(self, fname):
        """Initialize ConfigParser for a file and run parsing method."""
        self.jobparser.read(fname)
        self._parse_jobdescription()

    def _parse_jobdescription(self):
        """Parse details for an HRM job and check for sanity.
        .
        Use the ConfigParser object and assemble a dicitonary with the
        collected details that contains all the information for launching a new
        processing task. Raises Exceptions in case something unexpected is
        found in the given file.
        """
        # FIXME: currently only deconvolution jobs are supported, until hucore
        # will be able to do the other things like SNR estimation and
        # previewgen using templates as well!
        # TODO: group code into parsing and sanity-checking
        self._sections = self.jobparser.sections()
        # parse generic information, version, user etc.
        if not 'hrmjobfile' in self._sections:
            raise Exception("Error parsing job from %s." % self.name)
        try:
            self.job['ver'] = self.jobparser.get('hrmjobfile', 'version')
        except ConfigParser.NoOptionError:
            raise Exception("Can't find version in %s." % self.name)
        if not (self.job['ver'] == '2'):
            raise Exception("Unexpected version in %s." % self.job['ver'])
        try:
            self.job['user'] = self.jobparser.get('hrmjobfile', 'username')
        except ConfigParser.NoOptionError:
            raise Exception("Can't find username in %s." % self.name)
        try:
            self.job['type'] = self.jobparser.get('hrmjobfile', 'jobtype')
        except ConfigParser.NoOptionError:
            raise Exception("Can't find jobtype in %s." % self.name)
        # from here on a jobtype specific parsing must be done:
        if self.job['type'] == 'hucore':
            self._parse_job_hucore()
        else:
            raise Exception("Unknown jobtype '%s'" % self.job['type'])

    def _parse_job_hucore(self):
        """Do the specific parsing of "hucore" type jobfiles.
        .
        Parse the "hucore" and the "inputfiles" sections of HRM job
        configuration files.
        .
        Returns
        -------
        void
            All information is added to the "self.job" dict.
        """
        # the "hucore" section:
        try:
            self.job['exec'] = self.jobparser.get('hucore', 'executable')
        except ConfigParser.NoOptionError:
            raise Exception("Can't find executable in %s." % self.name)
        try:
            self.job['template'] = self.jobparser.get('hucore', 'template')
        except ConfigParser.NoOptionError:
            raise Exception("Can't find template in %s." % self.name)
        # and the input file(s):
        if not 'inputfiles' in self._sections:
            raise Exception("No input files defined in %s." % self.name)
        self.job['infiles'] = []
        for option in self.jobparser.options('inputfiles'):
            infile = self.jobparser.get('inputfiles', option)
            self.job['infiles'].append(infile)
