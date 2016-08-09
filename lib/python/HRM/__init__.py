#!/usr/bin/env python
# -*- coding: utf-8 -*-

import os
import pprint

from .logger import *


# expected version for job description files:
JOBFILE_VER = '7'


def setup_rundirs(base_dir):
    """Check if all runtime directories exist or try to create them otherwise.

    Assuming base_dir is '/run', the expected structure is like this:

    /run
        |-- queue
        |   |-- requests
        |   `-- status
        `-- spool
            |-- cur
            |-- done
            `-- new

    Parameters
    ----------
    base_dir : str
        Base path where to set up / check the run directories.

    Returns
    -------
    full_subdirs : {
        'new'      : '/run/spool/new',
        'cur'      : '/run/spool/cur',
        'done'     : '/run/spool/done',
        'requests' : '/run/queue/requests',
        'status'   : '/run/queue/status',
        'newfiles' : list of existing files in the 'new' directory
    }
    """
    full_subdirs = dict()
    tree = {
        'spool': ['new', 'cur', 'done'],
        'queue': ['status', 'requests']
    }
    for run_dir in tree:
        for sub_dir in tree[run_dir]:
            cur = os.path.join(base_dir, run_dir, sub_dir)
            if not os.access(cur, os.W_OK):
                if os.path.exists(cur):
                    raise OSError("Directory '%s' exists, but it is not "
                                  "writable for us. Stopping!" % cur)
                try:
                    os.makedirs(cur)
                    logi("Created spool directory '%s'.", cur)
                except OSError as err:
                    raise OSError("Error creating Queue Manager runtime "
                                  "directory '%s': %s" % (cur, err))
            full_subdirs[sub_dir] = cur

    # pick up any existing jobfiles in the 'new' spooldir
    full_subdirs['newfiles'] = list()
    new_existing = os.listdir(full_subdirs['new'])
    if new_existing:
        for fname in new_existing:
            logw("Found existing file in 'new' directory: %s", fname)
            full_subdirs['newfiles'].append(fname)
    logi("Runtime directories:\n%s", pprint.pformat(full_subdirs))

    # check the 'cur' directory and issue a warning only if non-empty:
    cur_existing = os.listdir(full_subdirs['cur'])
    if cur_existing:
        logw("%s WARNING %s", "=" * 60, "=" * 60)
        logw("Spooling directory '%s' non-empty, this could be due to an "
             "unclean shutdown of the Queue Manager!", full_subdirs['cur'])
        for fname in cur_existing:
            logw("- file: %s", os.path.join(full_subdirs['cur'], fname))
        logw("%s WARNING %s", "=" * 60, "=" * 60)
    return full_subdirs


