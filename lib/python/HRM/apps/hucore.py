#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
GC3lib application classes for HuCore related tasks.

Classes
-------

HuCoreApp()
HuDeconApp()
HuPreviewApp()
HuSNRApp()
    The gc3libs applications.
"""

import os
import gc3libs

from ..logger import *


class HuCoreApp(gc3libs.Application):

    """App object for generic 'hucore' jobs.

    This virtual application calls `hucore` with a given template file and
    retrives the stdout/stderr in a file named `stdout.txt` plus the
    directories `resultdir` and `previews` into a directory `reults_<UID>`
    inside the current directory.
    """

    def __init__(self, job, gc3_output):
        if self.__class__.__name__ == 'HuCoreApp':
            raise TypeError("Not instantiating the virtual class 'HuCoreApp'!")
        self.job = job   # remember the job object
        logw('Instantiating a %s:\n[%s]: %s --> %s',
             self.__class__.__name__,
             self.job['user'],
             self.job['template'],
             self.job['infiles'])
        uid = self.job['uid']
        logi('Job UID: %s', uid)
        # we need to add the template (with the local path) to the list of
        # files that need to be transferred to the system running hucore:
        self.job['infiles'].append(self.job['template'])
        # for the execution on the remote host, we need to strip all paths from
        # this string as the template file will end up in the temporary
        # processing directory together with all the images:
        templ_on_tgt = self.job['template'].split('/')[-1]
        super(HuCoreApp, self).__init__(
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
        # TODO: #407 process "output_dir" after job has terminated
        #       the results have to be put back to the user's destination
        #       directory (in case of gc3 remote execution, data might have
        #       been collected already)
        # TODO: consider specifying the output dir in the jobfile
        #       for now we use the gc3spooldir as the output_dir, so results
        #       will NOT get moved across different storage locations
        ##### hucore EXIT CODES #####
        # 0: all went well
        # 130: hucore.bin was terminated with Ctrl-C (interactive console)
        # 143: hucore.bin received the HUP signal (9)
        # 165: the .hgsb file could not be parsed (file missing or with errors)
        ##### hucore EXIT CODES #####
        self.status_changed()
        if self.execution.exitcode is None:
            # TODO: we could let the app know it was killed
            #       currently, were guessing from the exitcode 'None' that the
            #       app was explicitly killed by gc3pie - it would be cleaner
            #       to explicitly cover this situation e.g. in the spooler's
            #       cleanup() method by telling the app it is requested to stop
            logw("Job '%s' apparently was killed by gc3pie!", self.job['uid'])
        elif self.execution.exitcode != 0:
            # IMPORTANT: gc3pie does NOT seem to pass on the exit code of
            # hucore in this value, instead every non-zero exit code is
            # represented as 255 - which means we can NOT DERIVE from this how
            # hucore has finished!
            logc("Job '%s' terminated with unexpected EXIT CODE: %s!",
                 self.job['uid'], self.execution.exitcode)
        else:
            logi("Job '%s' terminated successfully!", self.job['uid'])
            logi("The output of the application is in `%s`.", self.output_dir)

    def status_changed(self):
        """Check the if the execution state of the app has changed.

        Track and update the internal execution status of the app and print a
        log message if the status changes. Return the new state if the app it
        has changed, otherwise None.
        """
        new = self.execution.state
        if new != self.laststate:
            logi("Job status changed from '%s' to '%s'.", self.laststate, new)
            self.laststate = self.job['status'] = new
            return new
        else:
            return None


class HuDeconApp(HuCoreApp):

    """App object for 'hucore' deconvolution jobs."""

    def __init__(self, job, gc3_output):
        super(HuDeconApp, self).__init__(job, gc3_output)


class HuPreviewApp(HuCoreApp):

    """App object for 'hucore' image preview generation jobs."""

    def __init__(self, job, gc3_output):
        super(HuPreviewApp, self).__init__(job, gc3_output)


class HuSNRApp(HuCoreApp):

    """App object for 'hucore' SNR estimation jobs."""

    def __init__(self, job, gc3_output):
        super(HuSNRApp, self).__init__(job, gc3_output)
