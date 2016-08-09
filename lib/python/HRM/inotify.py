# -*- coding: utf-8 -*-
"""
Helper module for pyinotify stuff.
"""

import os
import pyinotify

from .jobs import process_jobfile
from .logger import logi, logd


class JobFileHandler(object):
    """Wrapper class to set up inotify for incoming jobfiles."""

    def __init__(self, queues, dirs):
        """Initialize watch-manager and notifier."""
        self.watch_mgr = pyinotify.WatchManager()
        # mask which events to watch: pyinotify.IN_CREATE
        self.wdd = self.watch_mgr.add_watch(dirs['new'],
                                            pyinotify.IN_CREATE,
                                            rec=False)
        self.notifier = pyinotify.ThreadedNotifier(self.watch_mgr,
                                                   EventHandler(queues=queues,
                                                                dirs=dirs))
        self.notifier.start()

    def shutdown(self):
        """Clean up watch-manager and notifier."""
        self.watch_mgr.rm_watch(self.wdd.values())
        self.notifier.stop()


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
