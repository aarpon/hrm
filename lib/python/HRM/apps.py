#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Support for various HRM related tasks, GC3lib application classes.

Classes
-------

HucoreDeconvolveApp()
HucorePreviewgenApp()
HucoreEstimateSNRApp()
    The gc3libs applications.
"""

from .logger import *

import os
import gc3libs

class HucoreDeconvolveApp(gc3libs.Application):

    """App object for 'hucore' deconvolution jobs.

    This application calls `hucore` with a given template file and retrives the
    stdout/stderr in a file named `stdout.txt` plus the directories `resultdir`
    and `previews` into a directory `deconvolved` inside the current directory.
    """

    def __init__(self, job, gc3_output):
        self.job = job   # remember the job object
        uid = self.job['uid']
        logw('Instantiating a HucoreDeconvolveApp:\n[%s]: %s --> %s',
             self.job['user'], self.job['template'], self.job['infiles'])
        logi('Job UID: %s', uid)
        # we need to add the template (with the local path) to the list of
        # files that need to be transferred to the system running hucore:
        self.job['infiles'].append(self.job['template'])
        # for the execution on the remote host, we need to strip all paths from
        # this string as the template file will end up in the temporary
        # processing directory together with all the images:
        templ_on_tgt = self.job['template'].split('/')[-1]
        gc3libs.Application.__init__(
            self,
            arguments=[self.job['exec'],
                       '-exitOnDone',
                       '-noExecLog',
                       '-checkForUpdates', 'disable',
                       '-template', templ_on_tgt],
            inputs=self.job['infiles'],
            outputs=['resultdir', 'previews'],
            # collect the results in a subfolder of GC3Pie's spooldir:
            output_dir=os.path.join(gc3_output, 'results_%s' % uid),
            stderr='stdout.txt', # combine stdout & stderr
            stdout='stdout.txt'
        )
        self.laststate = self.execution.state

    def terminated(self):
        """This is called when the app has terminated execution."""
        # TODO: put the results dir back to the user's destination directory
        # (WARNING: we have to be careful if the data has already been
        # collected in case of a remote execution scenario)
        # TODO: consider specifying the output dir in the jobfile!
        # -> for now we simply use the gc3spooldir as the output directory to
        # ensure results won't get moved across different storage locations:
        # hucore EXIT CODES:
        # 0: all went well
        # 143: hucore.bin received the HUP signal (9)
        # 165: the .hgsb file could not be parsed (file missing or with errors)
        if self.execution.exitcode != 0:
            logc("Job '%s' terminated with unexpected EXIT CODE: %s!",
                 self.job['uid'], self.execution.exitcode)
        else:
            logi("Job '%s' terminated successfully!", self.job['uid'])
        logd("The output of the application is in `%s`.", self.output_dir)

    def status_changed(self):
        """Check the if the execution state of the app has changed.

        Track and update the internal execution status of the app and print a
        log message if the status changes. Return the new state if the app it
        has changed, otherwise None.
        """
        if self.execution.state != self.laststate:
            logi("Job status changed to '%s'.", self.job['status'])
            self.laststate = self.execution.state
            return self.execution.state
        else:
            return None


class HucorePreviewgenApp(gc3libs.Application):

    """App object for 'hucore' image preview generation jobs."""

    def __init__(self):
        # logw('Instantiating a HucorePreviewgenApp:\n%s', job)
        logw('WARNING: this is a stub, nothing is implemented yet!')
        super(HucorePreviewgenApp, self).__init__()


class HucoreEstimateSNRApp(gc3libs.Application):

    """App object for 'hucore' SNR estimation jobs."""

    def __init__(self):
        # logw('Instantiating a HucoreEstimateSNRApp:\n%s', job)
        logw('WARNING: this is a stub, nothing is implemented yet!')
        super(HucoreEstimateSNRApp, self).__init__()
