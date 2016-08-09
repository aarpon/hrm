# -*- coding: utf-8 -*-
"""
Helper module for pyinotify stuff.
"""

import os
import pyinotify

from .jobs import process_jobfile
from .logger import logi, logd


class EventHandler(pyinotify.ProcessEvent):
    """Handler for pyinotify filesystem events.

    An instance of this class can be registered as a handler to pyinotify and
    then gets called to process an event registered by pyinotify.

    Public Methods
    --------------
    process_IN_CREATE()
    """

    def my_init(self, queues, dirs):                # pylint: disable=W0221
        """Initialize the inotify event handler.

        Parameters
        ----------
        queues : dict
            Containing the JobQueue objects for the different queues, using the
            corresponding 'type' keyword as identifier.
        dirs : dict
            Spooling directories in a dict, as returned by HRM.setup_rundirs().
        """
        self.queues = queues
        self.dirs = dirs
        logi('Initialized the event handler for inotify, watching job '
             'submission directory "%s".', self.dirs['new'])

    def process_IN_CREATE(self, event):
        """Method handling 'create' events.

        Parameters
        ----------
        event : pyinotify.Event
        """
        logi("New file event '%s'", os.path.basename(event.pathname))
        logd("inotify 'IN_CREATE' event full file path '%s'", event.pathname)
        process_jobfile(event.pathname, self.queues, self.dirs)