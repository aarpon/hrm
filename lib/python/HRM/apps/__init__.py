# -*- coding: utf-8 -*-
"""
GC3lib based abstract application class.

Classes
-------

AbstractApp()
"""

import os
import gc3libs

from .. import logi, logd, logw, logc, loge


class AbstractApp(gc3libs.Application):

    """App object for generic gc3lib based jobs.

    This virtual application is to be used for deriving subclasses that share
    the common methods defined here.
    """

    def __init__(self, job, appconfig):
        """Set up the application.

        Parameters
        ----------
        job : HRM.jobs.JobDescription
        appconfig : dict
            A dict with at least all mandatory parameters for a
            gc3libs.Application, plus possibly extra parameters.
        """
        if self.__class__.__name__ == 'AbstractApp':
            raise TypeError("Refusing to instantiate class 'AbstractApp'!")
        self.job = job   # remember the job object
        logd('gc3_output_dir: %s', appconfig['output_dir'])
        logd('self.job: %s', job)
        logw('Instantiating a %s: [[user: %s]] [[uid: %s]]',
             self.__class__.__name__, job['user'], job['uid'])
        super(AbstractApp, self).__init__(**appconfig)
        self.laststate = self.execution.state

    def new(self):
        """Called when the job state is (re)set to NEW.

        Note this will not be called when the application object is created,
        rather if the state is reset to NEW after it has already been
        submitted.
        """
        self.status_changed()

    def running(self):
        """Called when the job state transitions to RUNNING."""
        self.status_changed()

    def stopped(self):
        """Called when the job state transitions to STOPPED."""
        self.status_changed()
        logc("Job '%s' has been suspended for an unknown reason!!!",
             self.job['uid'])

    def submitted(self):
        """Called when the job state transitions to SUBMITTED."""
        self.status_changed()

    def terminated(self):
        """This is called when the app has terminated execution."""
        self.status_changed()
        if self.execution.exitcode is None:
            # TODO: we could let the app know it was killed
            #       currently, were guessing from the exitcode 'None' that the
            #       app was explicitly killed by gc3pie - it would be cleaner
            #       to explicitly cover this situation e.g. in the spooler's
            #       cleanup() method by telling the app it is requested to stop
            logw("Job '%s' apparently was killed or crahsed!", self.job['uid'])
        elif self.execution.exitcode != 0:
            # IMPORTANT: gc3pie does NOT seem to pass on the exit code of
            # the job in this value, instead every non-zero exit code is
            # represented as 255 - which means we can NOT DERIVE from this how
            # the job process has finished!
            logc("Job '%s' terminated with unexpected EXIT CODE: %s!",
                 self.job['uid'], self.execution.exitcode)
        else:
            logi("Job '%s' terminated successfully!", self.job['uid'])
            logi("The output of the application is in `%s`.", self.output_dir)

    def terminating(self):
        """Called when the job state transitions to TERMINATING."""
        self.status_changed()

    def status_changed(self):
        """Check the if the execution state of the app has changed.

        Track and update the internal execution status of the app and print a
        log message if the status changes. Return the new state if the app it
        has changed, otherwise None.
        """
        new = self.execution.state
        if new != self.laststate:
            logi("%s: job status changed from '%s' to '%s'.",
                 self.__class__.__name__, self.laststate, new)
            self.laststate = self.job['status'] = new
            return new
        else:
            return None
