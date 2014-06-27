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
import pprint

__all__ = ['JobDescription']


# expected version for job description files:
JOBFILE_VER = '3'


class JobDescription(dict):

    """Abstraction class for handling HRM job descriptions.

    Read an HRM job description either from a file or a string and parse
    the sections, check them for sane values and store them in a dict.
    """

    def __init__(self, job, srctype):
        """Initialize depending on the type of description source.

        Parameters
        ----------
        job : string
        srctype : string

        Example
        -------
        >>> job = HRM.JobDescription('/path/to/jobdescription.cfg', 'file')
        """
        self.jobparser = ConfigParser.RawConfigParser()
        self._sections = []
        if (srctype == 'file'):
            self.name = "file '%s'" % job
            self._parse_jobfile(job)
        elif (srctype == 'string'):
            # TODO: _parse_jobstring(job)
            self.name = "string received from socket"
            raise Exception("Source type 'string' not yet implemented!")
        else:
            raise Exception("Unknown source type '%s'" % srctype)
        pprint.pprint("Finished initialization of JobDescription().")
        pprint.pprint(self)

    def _parse_jobfile(self, fname):
        """Initialize ConfigParser for a file and run parsing method."""
        self.jobparser.read(fname)
        self._parse_jobdescription()

    def _parse_jobdescription(self):
        """Parse details for an HRM job and check for sanity.

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
            raise ValueError("Error parsing job from %s." % self.name)
        try:
            self['ver'] = self.jobparser.get('hrmjobfile', 'version')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find version in %s." % self.name)
        if not (self['ver'] == JOBFILE_VER):
            raise ValueError("Unexpected version in %s." % self['ver'])
        try:
            self['user'] = self.jobparser.get('hrmjobfile', 'username')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find username in %s." % self.name)
        try:
            self['email'] = self.jobparser.get('hrmjobfile', 'useremail')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find email address in %s." % self.name)
        try:
            self['type'] = self.jobparser.get('hrmjobfile', 'jobtype')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find jobtype in %s." % self.name)
        # from here on a jobtype specific parsing must be done:
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
        # the "hucore" section:
        try:
            self['exec'] = self.jobparser.get('hucore', 'executable')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find executable in %s." % self.name)
        try:
            self['template'] = self.jobparser.get('hucore', 'template')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find template in %s." % self.name)
        # and the input file(s):
        if not 'inputfiles' in self._sections:
            raise ValueError("No input files defined in %s." % self.name)
        self['infiles'] = []
        for option in self.jobparser.options('inputfiles'):
            infile = self.jobparser.get('inputfiles', option)
            self['infiles'].append(infile)
