"""A convenience module to set up logging with some default values across
multiple modules.

Example
-------
>>> from hrm_logger import log

From there on a logger is available for usage with e.g. log.warn(), even if the
import statement from above happens in multiple places across modules, it will
always use the same logger instance (that "singleton" functionality is built
into the logging module, we just do the setup here). This can easily be checked
by looking at the log handlers in the different modules.

The logging levels, in increasing order of importance, are:

10: DEBUG
20: INFO
30: WARN
40: ERROR
50: CRITICAL
"""

import logging

log = logging.getLogger('hrm_logger')

# convenience aliases that can be explicitly imported if desired:
warn = log.warn
info = log.info
debug = log.debug

# we always log to stdout, so add a console handler to the logger
STREAM_HDL = logging.StreamHandler()
log.addHandler(STREAM_HDL)


def set_loglevel(verbosity):
    """Calculate the default loglevel and set it accordingly.

    This is a convenience function that wraps the calculation and setting of
    the logging level. The way our "log" module is currently built (as a
    singleton), there is no obvious better way to have this somewhere else.

    It is intended to set the verbosity level when using argparse to count the
    number of occurences of '-v' from the commandline. If no '-v' is supplied
    the loglevel will be WARN, for one '-v' it will be INFO and for two '-v' it
    will be DEBUG.
    """
    # default loglevel is 30 while 20 and 10 show more details
    loglevel = (3 - verbosity) * 10
    log.setLevel(loglevel)

def set_filehandler(fname, no_stderr=False, mode='a'):
    """Set the logging handler to a FileHandler.

    Optionally removes the StreamHandler that logs to stderr.

    Returns
    -------
    FILE_HDL : FileHandler
    """
    FILE_HDL = logging.FileHandler(fname, mode=mode)
    log.addHandler(FILE_HDL)
    if no_stderr:
        log.removeHandler(STREAM_HDL)
    return FILE_HDL

# from http://stackoverflow.com/questions/4722745
#
# formatter = logging.Formatter(
#     "%(asctime)s %(threadName)-11s %(levelname)-10s %(message)s")
# # Alternative formatting available on python 3.2+:
# # formatter = logging.Formatter(
# #     "{asctime} {threadName:>11} {levelname} {message}", style='{')
#
# # Log to file
# filehandler = logging.FileHandler("debug.txt", "w")
# filehandler.setLevel(logging.DEBUG)
# filehandler.setFormatter(formatter)
# log.addHandler(filehandler)
#
# # Log to stdout too
# streamhandler = logging.StreamHandler()
# streamhandler.setLevel(logging.INFO)
# streamhandler.setFormatter(formatter)
# log.addHandler(streamhandler)
