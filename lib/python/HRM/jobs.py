# -*- coding: utf-8 -*-
"""
Job description class module.

Classes
-------

JobDescription()
    Parser for job descriptions, works on files or strings.
"""

import ConfigParser
import StringIO
import os
import pprint
import shutil
import time
from hashlib import sha1

from . import logi, logd, logw, logc, loge, JOBFILE_VER


def process_jobfile(fname, queues, dirs):
    """Parse a jobfile and add it to its destination queue.

    Parameters
    ----------
    fname : str
        The name of the job file to parse.
    queues : dict
        Containing the JobQueue objects for the different queues, using the
        corresponding 'type' keyword as identifier.
    dirs : dict
        Spooling directories in a dict, as returned by HRM.setup_rundirs().
    """
    try:
        job = JobDescription(fname, 'file', dirs)
    except IOError as err:
        logw("Error reading job description file (%s), skipping.", err)
        # there is nothing to add to the queue and the IOError indicates
        # problems accessing the file, so we simply return silently:
        return
    except (SyntaxError, ValueError) as err:
        # jobfile was already moved out of the way by the constructor of the
        # JobDescription object, so we simply stop here and return:
        return
    if job['type'] == 'deletejobs':
        logw('Received job deletion request(s)!')
        # TODO: append only to specific queue!
        for queue in queues.itervalues():
            for delete_id in job['ids']:
                queue.deletion_list.append(delete_id)
        # we're finished, so move the jobfile and return:
        job.move_jobfile(dirs['done'])
        return
    if job['type'] not in queues:
        logc("ERROR: no queue existing for jobtype '%s'!", job['type'])
        job.move_jobfile(dirs['done'])
        return
    job.move_jobfile(dirs['cur'])
    # TODO: have more than one queue, decide by 'tasktype' where to put a job
    try:
        queues[job['type']].append(job)
    except ValueError as err:
        loge("Adding the newe job from '%s' failed:\n    %s", fname, err)



class JobDescription(dict):
    """Abstraction class for handling HRM job descriptions.

    Read an HRM job description either from a file or a string and parse
    the sections, check them for sane values and store them in a dict.

    Instance Variables
    ------------------
    jobparser : ConfigParser.RawConfigParser
    srctype : str
    fname : str
    _sections : list
    """

    def __init__(self, job, srctype, spooldirs):
        """Initialize depending on the type of description source.

        Parameters
        ----------
        job : string
            Can be either a filename pointing to a job config file, or a
            configuration itself, requires 'srctype' to be set accordingly!
        srctype : string
            One of ['file', 'string'], determines whether 'job' should be
            interpreted as a filename or as a job description string.
        spooldirs : dict
            The dict containing the queue's spooling directories.

        Example
        -------
        >>> job = HRM.JobDescription('/path/to/jobdescription.cfg', 'file')
        """
        super(JobDescription, self).__init__()
        self.jobparser = ConfigParser.RawConfigParser()
        self._sections = []
        self.spooldirs = spooldirs
        self.srctype = srctype
        if srctype == 'file':
            self.fname = job
            job = self._read_jobfile()
        elif srctype == 'string':
            self.fname = "string"
        else:
            raise Exception("Unknown source type '%s'" % srctype)
        # store the SHA1 digest of this job, serving as the UID:
        self['uid'] = sha1(job).hexdigest()
        try:
            self.parse_jobconfig(job)
        except (SyntaxError, ValueError) as err:
            logw("Job file unparsable (%s), skipping / moving to 'done'.", err)
            # move the unreadable file out of the way before returning:
            self.move_jobfile(self.spooldirs['done'], ".invalid")
            raise err
        # fill in keys without a reasonable value, they'll be updated later:
        self['status'] = "N/A"
        self['start'] = "N/A"
        self['progress'] = "N/A"
        self['pid'] = "N/A"
        self['server'] = "N/A"
        logd("Finished initialization of JobDescription().")
        logd(pprint.pformat(self))

    def move_jobfile(self, target, suffix=".jobfile"):
        """Move a jobfile to the desired spooling subdir.

        The file name will be set automatically to the job's UID with an
        added suffix ".jobfile", no matter how the file was called before.

        WARNING: destination file is not checked, if it exists and we have
        write permissions, it is simply overwritten!

        Parameters
        ----------
        target : str
            The target directory.
        suffix : str (optional)
            An optional suffix, by default ".jobfile" will be used if empty.
        """
        # make sure to only move "file" job descriptions, return otherwise:
        if self.srctype != 'file':
            return
        target = os.path.join(target, self['uid'] + suffix)
        if os.path.exists(target):
            target += ".%s" % time.time()
        logi("Moving file '%s' to '%s'.", self.fname, target)
        shutil.move(self.fname, target)
        # update the job's internal fname pointer:
        self.fname = target

    def _read_jobfile(self):
        """Read in a job config file and pass it to the parser.

        Returns
        -------
        config_raw : str
            The file content as a single string.
        """
        logi("Parsing jobfile '%s'...", os.path.basename(self.fname))
        logd("Full jobfile path: '%s'...", self.fname)
        if not os.path.exists(self.fname):
            raise IOError("Can't find file '%s'!" % self.fname)
        if not os.access(self.fname, os.R_OK):
            raise IOError("No permission reading file '%s'!" % self.fname)
        # sometimes the inotify event gets processed very rapidly and we're
        # trying to parse the file *BEFORE* it has been written to disk
        # entirely, which breaks the parsing, so we introduce four additional
        # levels of waiting time to avoid this race condition:
        config_raw = []
        for snooze in [0, 0.00001, 0.0001, 0.001, 0.01, 0.1]:
            if len(config_raw) == 0 and snooze > 0:
                logd("Jobfile could not be read, re-trying in %is.", snooze)
            time.sleep(snooze)
            with open(self.fname, 'r') as jobfile:
                config_raw = jobfile.read()
            if len(config_raw) > 0:
                logd("Job parsing succeeded after %s seconds!", snooze)
                break
        if len(config_raw) == 0:
            raise IOError("Unable to read job config file '%s'!" % self.fname)
        return config_raw

    def parse_jobconfig(self, cfg_raw):
        """Initialize ConfigParser and run parsing method."""
        try:
            self.jobparser.readfp(StringIO.StringIO(cfg_raw))
            logd("Parsed job configuration.")
        except ConfigParser.MissingSectionHeaderError as err:
            raise SyntaxError("ERROR in JobDescription: %s" % err)
        self._sections = self.jobparser.sections()
        if not self._sections:
            raise SyntaxError("No sections found in job config %s" % self.fname)
        logd("Job description sections: %s", self._sections)
        self._parse_jobdescription()

    def _get_option(self, section, option):
        """Helper method to get an option and remove it from the section."""
        value = self.jobparser.get(section, option)
        self.jobparser.remove_option(section, option)
        return value

    def _check_for_remaining_options(self, section):
        """Helper method to check if a section has remaining items."""
        remaining = self.jobparser.items(section)
        if remaining:
            raise ValueError("Section '%s' in file '%s' contains unknown "
                             "options, jobfile is invalid: %s" %
                             (section, self.fname, remaining))

    def _parse_section_entries(self, section, options_mapping):
        """Helper function to read a given list of options from a section.

        Parameters
        ----------
        section : str
            The name of the section to parse.
        options_mapping : list of tuples
            A list of tuples containing the mapping from the option names in
            the config file to the key names in the JobDescription object, e.g.

            mapping = [
                ['version', 'ver'],
                ['username', 'user'],
                ['useremail', 'email'],
                ['timestamp', 'timestamp'],
                ['jobtype', 'type']
            ]
        """
        if not self.jobparser.has_section(section):
            raise ValueError("Error parsing job from %s." % self.fname)
        for cfg_option, job_key in options_mapping:
            try:
                self[job_key] = self._get_option(section, cfg_option)
            except ConfigParser.NoOptionError:
                raise ValueError("Can't find %s in %s." %
                                 (cfg_option, self.fname))
                # raise ValueError("Jobfile %s invalid, '%s' is missing!" %
                #                  (self.fname, cfg_option))
        # by now the section should be fully parsed and therefore empty:
        self._check_for_remaining_options('hrmjobfile')

    def _parse_jobdescription(self):
        """Parse details for an HRM job and check for sanity.

        Use the ConfigParser object and assemble a dicitonary with the
        collected details that contains all the information for launching a new
        processing task. Raises Exceptions in case something unexpected is
        found in the given file.
        """
        # prepare the parser-mapping for the generic 'hrmjobfile' section:
        mapping = [
            ['version', 'ver'],
            ['username', 'user'],
            ['useremail', 'email'],
            ['timestamp', 'timestamp'],
            ['jobtype', 'type']
        ]
        # now parse the section:
        self._parse_section_entries('hrmjobfile', mapping)
        # sanity-check / validate the parsed options:
        if self['ver'] != JOBFILE_VER:
            raise ValueError("Unexpected jobfile version '%s'." % self['ver'])
        if self['timestamp'] == 'on_parsing':
            # the keyword "on_parsing" requires us to fill in the value:
            self['timestamp'] = time.time()
            # in this case we also adjust the UID of the job - this is mostly
            # done to allow submitting the same jobfile multiple times during
            # testing and should not be used in production, therefore we also
            # issue a corresponding warning message:
            self['uid'] = sha1(str(self['timestamp'])).hexdigest()
            logw('===%s', ' WARNING ===' * 8)
            logw('"timestamp = on_parsing" is meant for testing only!!!')
            logw('===%s', ' WARNING ===' * 8)
        else:
            # otherwise we need to convert to float, or raise an error:
            try:
                self['timestamp'] = float(self['timestamp'])
            except ValueError:
                raise ValueError("Invalid timestamp: %s." % self['timestamp'])
        # now call the jobtype-specific parser method(s):
        if self['type'] == 'hucore':
            self._parse_job_hucore()
        elif self['type'] == 'deletejobs':
            self._parse_job_deletejobs()
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
        # prepare the parser-mapping for the generic 'hrmjobfile' section:
        mapping = [
            ['tasktype', 'tasktype'],
            ['executable', 'exec'],
            ['template', 'template']
        ]
        # now parse the section:
        self._parse_section_entries('hucore', mapping)
        if self['tasktype'] != 'decon' and self['tasktype'] != 'preview':
            raise ValueError("Tasktype invalid: %s" % self['tasktype'])
        # and the input file(s) section:
        # TODO: can we check if this section contains nonsense values?
        if 'inputfiles' not in self._sections:
            raise ValueError("Section 'inputfiles' missing in %s." % self.fname)
        self['infiles'] = []
        for option in self.jobparser.options('inputfiles'):
            infile = self._get_option('inputfiles', option)
            self['infiles'].append(infile)
        if not self['infiles']:
            raise ValueError("No input files defined in %s." % self.fname)

    def _parse_job_deletejobs(self):
        """Do the specific parsing of "deletejobs" type jobfiles.

        Returns
        -------
        void
            All information is added to the "self" dict.
        """
        if 'deletejobs' not in self._sections:
            raise ValueError("No 'deletejobs' section in %s." % self.fname)
            # raise ValueError("No job ID's defined in %s." % self.fname)
        try:
            jobids = self._get_option('deletejobs', 'ids')
        except ConfigParser.NoOptionError:
            raise ValueError("Can't find jobids in %s." % self.fname)
        # split string at commas, strip whitespace from components:
        self['ids'] = [jobid.strip() for jobid in jobids.split(',')]
        for jobid in self['ids']:
            logi("Request to --- DELETE --- job '%s'", jobid)

    def get_category(self):
        """Get the category of this job, in our case the value of 'user'."""
        return self['user']
